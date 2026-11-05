-- Navegação no framework da disciplina (MVC próprio, PHP).
--
-- Convenções usadas (as do projeto Eventos do Câmpus):
--   app/Controllers/Admin/EventsController.php  -> views em
--   app/views/admin/events/<ação>.phtml (o render('admin/events/x')
--   dentro da ação manda; sem render, vale o nome da ação);
--   app/Models/Event.php <-> tests/Unit/Models/Events/EventTest.php
--   (qualquer arquivo X.php de app/, core/ ou lib/ <-> tests/**/XTest.php);
--   config/routes.php: [AliasController::class, 'acao'] <-> método.
local M = {}

local uv = vim.uv or vim.loop

local function exists(path)
  return path ~= nil and uv.fs_stat(path) ~= nil
end

local function notify(msg, level)
  vim.notify(msg, level or vim.log.levels.INFO, { title = "framework" })
end
M.notify = notify

local function readlines(path)
  local ok, lines = pcall(vim.fn.readfile, path)
  return ok and lines or {}
end

local function buf_path(buf)
  buf = (buf == nil or buf == 0) and vim.api.nvim_get_current_buf() or buf
  local name = vim.api.nvim_buf_get_name(buf)
  if name == "" or vim.bo[buf].buftype ~= "" then
    return nil
  end
  return vim.fs.normalize(vim.fn.fnamemodify(name, ":p"))
end
M.buf_path = buf_path

-- Raiz do framework: sobe a partir do arquivo (ou da pasta atual) e
-- fica com a primeira pasta que tem composer.json e o script ./run.
-- Sem nenhuma assim, a primeira com composer.json. Senão, nil.
function M.root(buf)
  local path = buf_path(buf)
  local start = path and vim.fs.dirname(path) or uv.cwd()
  local first
  for dir in vim.fs.parents(start .. "/x") do
    if exists(dir .. "/composer.json") then
      if exists(dir .. "/run") then
        return dir
      end
      first = first or dir
    end
  end
  if path then
    -- buffer fora de um projeto: tenta a pasta atual
    local cwd = uv.cwd()
    if exists(cwd .. "/composer.json") and exists(cwd .. "/run") then
      return cwd
    end
  end
  return first
end

-- Raiz para o intelephense: a do framework; fora dele, composer.json
-- ou .git mais próximos; senão a pasta do arquivo.
function M.lsp_root(buf)
  local root = M.root(buf)
  if root then
    return root
  end
  local path = buf_path(buf)
  if not path then
    return uv.cwd()
  end
  return vim.fs.root(path, { "composer.json", ".git" }) or vim.fs.dirname(path)
end

-- Caminho relativo à raiz (nil se o arquivo estiver fora dela).
function M.rel(path, root)
  if not (path and root) then
    return nil
  end
  local prefix = root .. "/"
  if path:sub(1, #prefix) == prefix then
    return path:sub(#prefix + 1)
  end
  return nil
end

-- Exige estar num projeto do framework; devolve root, caminho, rel.
local function context(buf)
  local root = M.root(buf)
  if not root then
    notify(
      "fora de um projeto do framework (sem composer.json e ./run)",
      vim.log.levels.WARN
    )
    return nil
  end
  local path = buf_path(buf)
  return root, path, M.rel(path, root)
end
M.context = context

-- Ferramenta nativa (phpcs, phpcbf, phpstan): vendor/bin do projeto
-- (com php no PATH) ou a do PATH (o 09 instala em
-- ~/.config/composer/vendor/bin). nil se não houver.
function M.native_tool(buf, name)
  if vim.fn.executable("php") ~= 1 then
    return nil
  end
  local root = M.root(buf)
  if root and vim.fn.executable(root .. "/vendor/bin/" .. name) == 1 then
    return root .. "/vendor/bin/" .. name
  end
  if vim.fn.executable(name) == 1 then
    return vim.fn.exepath(name)
  end
  return nil
end

-- Abre um arquivo e, se dado, põe o cursor na linha (1-based).
function M.open(path, lnum)
  vim.cmd.edit(vim.fn.fnameescape(path))
  if lnum then
    pcall(vim.api.nvim_win_set_cursor, 0, { lnum, 0 })
    vim.cmd("normal! ^zz")
  end
end

-- Linha (1-based) da declaração "function <nome>(" no arquivo.
local function find_function_line(lines, name)
  for i, line in ipairs(lines) do
    if line:match("function%s+" .. vim.pesc(name) .. "%s*%(") then
      return i
    end
  end
  return nil
end
M.find_function_line = find_function_line

-- Método sob o cursor: nome, linha da declaração, linha do "}" final.
function M.current_method(buf, lnum)
  buf = (buf == nil or buf == 0) and vim.api.nvim_get_current_buf() or buf
  lnum = lnum or vim.api.nvim_win_get_cursor(0)[1]
  local lines = vim.api.nvim_buf_get_lines(buf, 0, -1, false)
  for i = math.min(lnum, #lines), 1, -1 do
    local indent, name = lines[i]:match("^(%s*)[%w%s]*function%s+([%w_]+)%s*%(")
    if name then
      local last = #lines
      for j = i + 1, #lines do
        if lines[j] == indent .. "}" then
          last = j
          break
        end
      end
      if i == lnum or lnum <= last then
        return name, i, last, lines
      end
      return nil
    end
  end
  return nil
end

local function pascal(s)
  return (s:gsub("_(%w)", string.upper):gsub("^%l", string.upper))
end

local function snake(s)
  return (s:gsub("(%l)(%u)", "%1_%2"):lower())
end

-- app/Controllers/Admin/EventsController.php -> "admin/events"
local function controller_view_dir(rel)
  local sub = rel:match("^app/Controllers/(.+)Controller%.php$")
  if not sub then
    return nil
  end
  local parts = vim.split(sub, "/", { plain = true })
  for i, p in ipairs(parts) do
    parts[i] = snake(p)
  end
  return table.concat(parts, "/")
end

-- "admin/events" -> "app/Controllers/Admin/EventsController.php"
local function view_dir_controller(dir)
  local parts = vim.split(dir, "/", { plain = true })
  for i, p in ipairs(parts) do
    parts[i] = pascal(p)
  end
  return "app/Controllers/" .. table.concat(parts, "/") .. "Controller.php"
end

-- Controller -> view da ação sob o cursor.
function M.goto_view()
  local root, _, rel = context()
  if not root then
    return
  end
  if not (rel and rel:match("^app/Controllers/.+%.php$")) then
    notify("abra um controller (app/Controllers/...)", vim.log.levels.WARN)
    return
  end
  local dir = controller_view_dir(rel)
  local name, first, last, lines = M.current_method(0)
  local candidates = {}
  if name then
    for i = first, last do
      local view = lines[i]:match("render%s*%(%s*['\"]([^'\"]+)['\"]")
      if view then
        table.insert(candidates, "app/views/" .. view .. ".phtml")
      end
    end
    if dir then
      table.insert(candidates, "app/views/" .. dir .. "/" .. name .. ".phtml")
    end
  end
  for _, c in ipairs(candidates) do
    if exists(root .. "/" .. c) then
      return M.open(root .. "/" .. c)
    end
  end
  local views = dir and root .. "/app/views/" .. dir
  if views and exists(views) then
    if name then
      local msg = "a ação '%s' não tem view; escolha uma de app/views/%s"
      notify(msg:format(name, dir))
    end
    return M.pick_files(views)
  end
  notify("não achei a view desta ação", vim.log.levels.WARN)
end

-- View -> controller, na ação que faz render dela.
function M.goto_controller()
  local root, _, rel = context()
  if not root then
    return
  end
  local view = rel and rel:match("^app/views/(.+)%.phtml$")
  if not view then
    notify("abra uma view (app/views/...)", vim.log.levels.WARN)
    return
  end
  -- 1) quem faz render('<view>')
  local pat = "render%s*%(%s*['\"]" .. vim.pesc(view) .. "['\"]"
  local files = vim.fs.find(function(n)
    return n:match("Controller%.php$")
  end, { path = root .. "/app/Controllers", type = "file", limit = math.huge })
  for _, f in ipairs(files) do
    local lines = readlines(f)
    for i, line in ipairs(lines) do
      if line:match(pat) then
        for j = i, 1, -1 do
          if lines[j]:match("function%s+[%w_]+%s*%(") then
            return M.open(f, j)
          end
        end
        return M.open(f, i)
      end
    end
  end
  -- 2) pela convenção de pastas (partials como _form caem aqui)
  local dir, base = view:match("^(.+)/([^/]+)$")
  local ctrl = dir and root .. "/" .. view_dir_controller(dir)
  if ctrl and exists(ctrl) then
    return M.open(ctrl, find_function_line(readlines(ctrl), base))
  end
  notify("não achei o controller desta view", vim.log.levels.WARN)
end

-- Pontua candidatos pelo número de pastas em comum (sem diferenciar
-- maiúsculas e plural simples: Events ~ event).
local function norm_parts(rel)
  local out = {}
  for part in vim.fs.dirname(rel):gmatch("[^/]+") do
    local p = part:lower():gsub("s$", "")
    out[p] = true
  end
  return out
end

local function best_match(rel, candidates, root)
  local mine, scored = norm_parts(rel), {}
  for _, c in ipairs(candidates) do
    local crel = M.rel(c, root)
    local score = 0
    for p in pairs(norm_parts(crel)) do
      if mine[p] then
        score = score + 1
      end
    end
    table.insert(scored, { path = c, rel = crel, score = score })
  end
  table.sort(scored, function(a, b)
    if a.score ~= b.score then
      return a.score > b.score
    end
    return a.rel < b.rel
  end)
  return scored
end

local function choose(scored, prompt)
  if #scored == 1 or (scored[2] and scored[1].score > scored[2].score) then
    return M.open(scored[1].path)
  end
  vim.ui.select(scored, {
    prompt = prompt,
    format_item = function(item)
      return item.rel
    end,
  }, function(item)
    if item then
      M.open(item.path)
    end
  end)
end

local function find_named(root, dirs, filename)
  local found = {}
  for _, d in ipairs(dirs) do
    if exists(root .. "/" .. d) then
      vim.list_extend(found, vim.fs.find(filename, {
        path = root .. "/" .. d, type = "file", limit = math.huge,
      }))
    end
  end
  return found
end

-- Caminho do teste de um arquivo (o melhor candidato), ou nil.
function M.test_for(root, rel)
  local base = rel:match("([^/]+)%.php$")
  if not base or rel:match("^tests/") then
    return nil
  end
  local found = find_named(root, { "tests" }, base .. "Test.php")
  local scored = best_match(rel, found, root)
  return scored[1] and scored[1].path or nil
end

-- Código <-> teste. Model -> tests/Unit/Models/**/XTest.php,
-- controller -> tests/Integration/Controllers/**/XControllerTest.php,
-- teste -> classe testada. Cest de aceitação -> controller da pasta.
function M.goto_test()
  local root, _, rel = context()
  if not (root and rel) then
    return
  end
  local test_base = rel:match("^tests/.*/([^/]+)Test%.php$")
  if test_base then
    local found =
      find_named(root, { "app", "core", "lib" }, test_base .. ".php")
    local scored = best_match(rel, found, root)
    if #scored == 0 then
      local msg = ("não achei %s.php em app/, core/ ou lib/"):format(test_base)
      return notify(msg, vim.log.levels.WARN)
    end
    return choose(scored, "Código testado:")
  end
  local cest_dir = rel:match("^tests/Acceptance/(.+)/[^/]+Cest%.php$")
  if cest_dir then
    local ctrl = root .. "/" .. view_dir_controller(cest_dir)
    if exists(ctrl) then
      return M.open(ctrl)
    end
    local msg = "não achei o controller de tests/Acceptance/" .. cest_dir
    return notify(msg, vim.log.levels.WARN)
  end
  local top = rel:match("^([^/]+)/")
  local base = rel:match("([^/]+)%.php$")
  local code = top == "app" or top == "core" or top == "lib"
  if not base or not code or rel:match("^app/views/") then
    return notify(
      "abra um model, controller, classe de core/lib ou um teste",
      vim.log.levels.WARN
    )
  end
  local found = find_named(root, { "tests" }, base .. "Test.php")
  local scored = best_match(rel, found, root)
  if #scored == 0 then
    local hint = rel:match("^app/Models/")
        and ("tests/Unit/Models/%ss/%sTest.php"):format(base, base)
      or rel:match("^app/Controllers/")
        and ("tests/Integration/Controllers/%sTest.php"):format(
          rel:match("^app/Controllers/(.+)%.php$")
        )
      or ("tests/Unit/.../%sTest.php"):format(base)
    local msg = "sem teste para %s (sugestão: %s; snippet fwtesteunit/fwteste)"
    return notify(msg:format(base, hint))
  end
  return choose(scored, "Teste:")
end

-- Model do recurso (EventsController, views/admin/events, testes
-- de events -> app/Models/Event.php).
function M.goto_model()
  local root, _, rel = context()
  if not (root and rel) then
    return
  end
  local resource = rel:match("^app/Controllers/.-([%w]+)Controller%.php$")
    or rel:match("^app/views/.-([%w_]+)/[^/]+%.phtml$")
    or rel:match("^tests/.-([%w]+)ControllerTest%.php$")
    or rel:match("^tests/Acceptance/.-([%w_]+)/[^/]+Cest%.php$")
    or rel:match("^tests/Unit/Models/.-([%w]+)Test%.php$")
  if not resource then
    local msg = "não sei qual model corresponde a este arquivo"
    return notify(msg, vim.log.levels.WARN)
  end
  local singular = resource:gsub("ies$", "y"):gsub("s$", "")
  for _, name in ipairs({ pascal(singular), pascal(resource) }) do
    local path = root .. "/app/Models/" .. name .. ".php"
    if exists(path) then
      return M.open(path)
    end
  end
  local msg = ("não achei app/Models/%s.php"):format(pascal(singular))
  notify(msg, vim.log.levels.WARN)
end

-- psr-4 do composer.json: { ["App\\"] = "app/", ... }
local function psr4(root)
  local text = table.concat(readlines(root .. "/composer.json"), "\n")
  local ok, data = pcall(vim.json.decode, text)
  local autoload = ok and type(data) == "table" and data.autoload
  local map = autoload and autoload["psr-4"] or {}
  if vim.tbl_isempty(map) then
    map = { ["App\\"] = "app/", ["Core\\"] = "core/", ["Lib\\"] = "lib/" }
  end
  return map
end

local function class_to_path(root, fqcn)
  fqcn = fqcn:gsub("^\\", "")
  for prefix, dir in pairs(psr4(root)) do
    if fqcn:sub(1, #prefix) == prefix then
      local rest = fqcn:sub(#prefix + 1):gsub("\\", "/")
      return root .. "/" .. dir:gsub("/?$", "/") .. rest .. ".php"
    end
  end
  return nil
end

-- [AliasController::class, 'acao'] numa linha de config/routes.php
local ACTION_PAT =
  "%[%s*([%w_\\]+)::class%s*,%s*['\"]([%w_]+)['\"]%s*%]"

-- "use X\Y\Z as W;" de routes.php: { W = "X\Y\Z", Z = ... }
local function route_aliases(lines)
  local map = {}
  for _, line in ipairs(lines) do
    local fq, alias = line:match("^use%s+([%w_\\]+)%s+as%s+([%w_]+)%s*;")
    if not fq then
      fq = line:match("^use%s+([%w_\\]+)%s*;")
      alias = fq and fq:match("([%w_]+)$")
    end
    if fq then
      map[alias] = fq
    end
  end
  return map
end

-- Classe completa do arquivo PHP aberto (namespace + nome do arquivo).
local function current_class(lines, path)
  for _, line in ipairs(lines) do
    local ns = line:match("^namespace%s+([%w_\\]+)%s*;")
    if ns then
      return ns .. "\\" .. vim.fn.fnamemodify(path, ":t:r")
    end
  end
  return nil
end

-- Na linha de uma rota: abre o método do controller.
local function route_line_to_action(root, routes, lnum)
  local lines = readlines(routes)
  local cls, action
  for i = lnum, math.max(1, lnum - 2), -1 do
    cls, action = lines[i]:match(ACTION_PAT)
    if cls then
      break
    end
  end
  if not cls then
    return false
  end
  local fq = route_aliases(lines)[cls] or cls
  local path = class_to_path(root, fq)
  if not (path and exists(path)) then
    notify("não achei o arquivo de " .. fq, vim.log.levels.WARN)
    return true
  end
  M.open(path, find_function_line(readlines(path), action))
  return true
end

-- route('nome') sob o cursor: abre a rota com ->name('nome').
local function named_route(root, line)
  local name = line:match("route%s*%(%s*['\"]([%w%._%-]+)['\"]")
  if not name then
    return false
  end
  local routes = root .. "/config/routes.php"
  for i, l in ipairs(readlines(routes)) do
    local single = l:find("->name('" .. name .. "')", 1, true)
    local double = l:find('->name("' .. name .. '")', 1, true)
    if single or double then
      M.open(routes, i)
      return true
    end
  end
  local msg = "rota '" .. name .. "' não está em config/routes.php"
  notify(msg, vim.log.levels.WARN)
  return true
end

-- Rota <-> ação. Em config/routes.php: da linha da rota para o método.
-- Num controller: do método sob o cursor para a(s) rota(s); com mais
-- de uma (ex.: index e paginate), vão para a quickfix (]q próxima).
-- Numa linha com route('nome'): vai para a rota com esse nome.
function M.goto_route()
  local root, path, rel = context()
  if not root then
    return
  end
  local routes = root .. "/config/routes.php"
  local lnum = vim.api.nvim_win_get_cursor(0)[1]
  local line = vim.api.nvim_get_current_line()
  if rel == "config/routes.php" then
    if route_line_to_action(root, routes, lnum) then
      return
    end
    return notify(
      "ponha o cursor numa linha Route::...([Controller::class, 'acao'])",
      vim.log.levels.WARN
    )
  end
  if named_route(root, line) then
    return
  end
  if not (rel and rel:match("^app/Controllers/.+%.php$")) then
    return M.open(routes)
  end
  local name = M.current_method(0)
  local fq = current_class(vim.api.nvim_buf_get_lines(0, 0, -1, false), path)
  if not (name and fq) then
    return M.open(routes)
  end
  local lines = readlines(routes)
  local names = { [fq] = true }
  for alias, full in pairs(route_aliases(lines)) do
    if full == fq then
      names[alias] = true
    end
  end
  local hits = {}
  for i, l in ipairs(lines) do
    local cls, action = l:match(ACTION_PAT)
    local mine = cls and (names[cls] or names[cls:gsub("^\\", "")])
    if mine and action == name then
      table.insert(hits, {
        filename = routes, lnum = i, col = 1, text = vim.trim(l),
      })
    end
  end
  if #hits == 0 then
    local msg = ("nenhuma rota aponta para %s::%s")
      :format(fq:match("[^\\]+$"), name)
    notify(msg, vim.log.levels.WARN)
    return M.open(routes)
  end
  if #hits > 1 then
    vim.fn.setqflist({}, " ", { title = "Rotas de " .. name, items = hits })
    notify(("%d rotas para '%s': ]q vai para a próxima"):format(#hits, name))
  end
  M.open(routes, hits[1].lnum)
end

-- Alternar conforme o arquivo: controller -> view, view -> controller,
-- routes.php -> ação, teste -> código, resto (model, core, lib) -> teste.
function M.alternate()
  local root, _, rel = context()
  if not (root and rel) then
    return
  end
  if rel:match("^app/Controllers/") then
    return M.goto_view()
  elseif rel:match("^app/views/") then
    return M.goto_controller()
  elseif rel == "config/routes.php" then
    return M.goto_route()
  end
  return M.goto_test()
end

-- Picker de arquivos (ou de texto, com grep = true) numa pasta.
function M.pick_files(dir, grep)
  if not exists(dir) then
    return notify("pasta inexistente: " .. dir, vim.log.levels.WARN)
  end
  if _G.LazyVim and LazyVim.pick then
    return LazyVim.pick(grep and "live_grep" or "files", { cwd = dir })()
  end
  vim.cmd.edit(vim.fn.fnameescape(dir))
end

-- Atalhos de busca nas pastas do projeto.
function M.pick(sub, grep)
  local root = context()
  if root then
    M.pick_files(root .. "/" .. sub, grep)
  end
end

function M.edit(sub)
  local root = context()
  if not root then
    return
  end
  if not exists(root .. "/" .. sub) then
    return notify("não existe " .. sub, vim.log.levels.WARN)
  end
  M.open(root .. "/" .. sub)
end

return M

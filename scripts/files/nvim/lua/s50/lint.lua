-- Diagnósticos de phpcs e phpstan no buffer (sinais na margem, ]d/[d,
-- <Espaço>xx), com as regras do projeto (phpcs.xml e phpstan.neon da
-- raiz).
--
-- - Nativo: phpcs/phpstan de vendor/bin (com php no PATH) ou do PATH
--   (o 09 instala). Roda sozinho (lua/config/autocmds.lua): phpcs ao
--   abrir, ao sair da inserção e ao salvar, lendo o buffer; phpstan
--   ao salvar, no arquivo em disco.
-- - Container: sem PHP nativo, nada roda sozinho. <Espaço>pl (ou
--   :PhpLint) chama "docker compose run --rm -T php ./vendor/bin/..."
--   só para o arquivo atual, sob demanda.
local fw = require("s50.framework")
local M = {}

local ns = {
  phpcs = vim.api.nvim_create_namespace("s50_phpcs"),
  phpstan = vim.api.nvim_create_namespace("s50_phpstan"),
}
local running = {} -- [buf .. tool] = vim.SystemObj
local warned = {}

-- phpstan só analisa o que o phpstan.neon cobre.
local function phpstan_covers(rel)
  return (rel:match("^app/") or rel:match("^core/") or rel:match("^tests/"))
    and not rel:match("^app/views/")
    and not rel:match("^tests/Support/")
    and not rel:match("^tests/Acceptance/_output/")
end

local function applies(tool, rel)
  if not (rel and rel:match("%.php$")) or rel:match("^vendor/") then
    return false
  end
  return tool == "phpcs" or phpstan_covers(rel)
end

local function decode(out)
  if not out or vim.trim(out) == "" then
    return nil
  end
  -- docker compose pode escrever antes do JSON; pega do primeiro "{".
  local start = out:find("{", 1, true)
  local ok, data = pcall(vim.json.decode, start and out:sub(start) or out)
  return ok and type(data) == "table" and data or nil
end

local function phpcs_diags(data)
  local diags = {}
  for _, file in pairs(data.files or {}) do
    for _, m in ipairs(file.messages or {}) do
      table.insert(diags, {
        lnum = math.max(0, (m.line or 1) - 1),
        col = math.max(0, (m.column or 1) - 1),
        message = m.message,
        code = m.source,
        source = "phpcs",
        severity = m.type == "ERROR" and vim.diagnostic.severity.ERROR
          or vim.diagnostic.severity.WARN,
      })
    end
  end
  return diags
end

local function phpstan_diags(data)
  local diags = {}
  for _, file in pairs(data.files or {}) do
    for _, m in ipairs(file.messages or {}) do
      table.insert(diags, {
        lnum = math.max(0, (tonumber(m.line) or 1) - 1),
        col = 0,
        message = m.message,
        code = m.identifier,
        source = "phpstan",
        severity = vim.diagnostic.severity.ERROR,
      })
    end
  end
  for _, err in ipairs(data.errors or {}) do
    table.insert(diags, {
      lnum = 0, col = 0, message = tostring(err), source = "phpstan",
      severity = vim.diagnostic.severity.WARN,
    })
  end
  return diags
end

-- Monta o comando. mode = "native" | "container".
local function command(tool, mode, buf, rel)
  local bin = mode == "native" and fw.native_tool(buf, tool)
    or ("./vendor/bin/" .. tool)
  local cmd, stdin
  if tool == "phpcs" then
    if mode == "native" then
      -- lê o buffer (vale também para o que ainda não foi salvo)
      cmd = { bin, "-q", "--report=json", "--stdin-path=" .. rel, "-" }
      local lines = vim.api.nvim_buf_get_lines(buf, 0, -1, false)
      stdin = table.concat(lines, "\n") .. "\n"
    else
      cmd = { bin, "-q", "--report=json", rel }
    end
  else
    cmd = {
      bin, "analyse", "--error-format=json", "--no-progress",
      "--memory-limit=1G", rel,
    }
  end
  if mode == "container" then
    local docker = { "docker", "compose", "run", "--rm", "-T", "php" }
    cmd = vim.list_extend(docker, cmd)
  end
  return cmd, stdin
end

-- Roda uma ferramenta num buffer. cb(ok, n_diagnosticos, erro).
function M.run(buf, tool, mode, cb)
  buf = (buf == nil or buf == 0) and vim.api.nvim_get_current_buf() or buf
  cb = cb or function() end
  local root = fw.root(buf)
  local rel = root and fw.rel(fw.buf_path(buf), root)
  if not applies(tool, rel) then
    return cb(false, 0, "fora do escopo de " .. tool)
  end
  if mode == "native" and not fw.native_tool(buf, tool) then
    return cb(false, 0, tool .. " nativo não encontrado")
  end
  local id = buf .. tool
  if running[id] then
    pcall(running[id].kill, running[id], 15)
  end
  local cmd, stdin = command(tool, mode, buf, rel)
  local proc_ref = {}
  local sysopts = { cwd = root, text = true, stdin = stdin }
  local ok, proc = pcall(vim.system, cmd, sysopts, function(res)
    vim.schedule(function()
      if running[id] == proc_ref.proc then
        running[id] = nil
      end
      -- morto por uma execução mais nova: ignora
      if (res.signal or 0) ~= 0 or not vim.api.nvim_buf_is_valid(buf) then
        return
      end
      local data = decode(res.stdout)
      if not data then
        local err = vim.trim((res.stderr or "") .. "\n" .. (res.stdout or ""))
        err = err ~= "" and err:sub(1, 400) or ("código " .. res.code)
        return cb(false, 0, err)
      end
      local diags = tool == "phpcs" and phpcs_diags(data) or phpstan_diags(data)
      vim.diagnostic.set(ns[tool], buf, diags)
      cb(true, #diags)
    end)
  end)
  if not ok then
    return cb(false, 0, tostring(proc))
  end
  proc_ref.proc = proc
  running[id] = proc
end

-- Execução automática: só nativa, sem avisos repetidos.
function M.auto(buf, tool)
  if not fw.native_tool(buf, tool) then
    return
  end
  M.run(buf, tool, "native", function(ok, _, err)
    local out_of_scope = err and err:match("^fora do escopo")
    if not ok and err and not out_of_scope and not warned[tool] then
      warned[tool] = true
      local msg = tool .. " falhou (aviso único nesta sessão):\n" .. err
      vim.notify(msg, vim.log.levels.WARN, { title = "lint" })
    end
  end)
end

-- <Espaço>pl / :PhpLint: phpcs + phpstan do arquivo atual, nativo se
-- der, senão pelo container. Resultado no canto e nos diagnósticos.
function M.lint_current()
  local buf = vim.api.nvim_get_current_buf()
  local root, _, rel = fw.context(buf)
  if not root then
    return
  end
  if not (rel and rel:match("%.php$")) then
    return fw.notify("abra um arquivo .php do projeto", vim.log.levels.WARN)
  end
  if vim.bo[buf].modified then
    vim.cmd.write()
  end
  for _, tool in ipairs({ "phpcs", "phpstan" }) do
    if applies(tool, rel) then
      local mode = fw.native_tool(buf, tool) and "native" or "container"
      local how = mode == "native" and "nativo" or "container"
      vim.notify(("%s (%s)..."):format(tool, how), vim.log.levels.INFO,
        { title = "lint" })
      M.run(buf, tool, mode, function(ok, n, err)
        if ok then
          local level = n == 0 and vim.log.levels.INFO or vim.log.levels.WARN
          vim.notify(("%s: %d problema(s)"):format(tool, n), level,
            { title = "lint" })
        else
          local msg = tool .. " falhou:\n" .. (err or "?")
          vim.notify(msg, vim.log.levels.ERROR, { title = "lint" })
        end
      end)
    end
  end
end

return M

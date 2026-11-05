-- Comandos do ./run do projeto dentro do Neovim.
--
-- Cada comando abre um terminal numa janela flutuante, na raiz do
-- projeto, com a saída colorida do phpunit, phpcs etc. O terminal fica
-- aberto no fim: "q" esconde, e o aviso no canto diz se terminou bem
-- (código 0) ou não. Repetir o mesmo comando com ele ainda rodando só
-- mostra a janela de novo.
--
-- A janela é feita aqui (nvim_open_win + jobstart), e não com o
-- Snacks.terminal do LazyVim: com um Snacks.terminal já terminado na
-- tela, o ":qa" deixava o Neovim travado na saída (snacks 882c996,
-- Neovim 0.12.6).
local fw = require("s50.framework")
local M = {}

local terms = {} -- chave (args) -> terminal
M.last = nil -- últimos args, para <Espaço>tl

local function key(args)
  return table.concat(args, " ")
end

local function on_exit(args, code)
  local result = code == 0 and "OK" or ("falhou (código %d)"):format(code)
  if args[1] == "phpcbf" and code == 1 then
    -- phpcbf: 1 = corrigiu tudo o que achou; 2 = sobrou erro
    result, code = "corrigiu arquivos (confira o git diff)", 0
  end
  local msg = ("./run %s: %s"):format(key(args), result)
  vim.schedule(function()
    local level = code == 0 and vim.log.levels.INFO or vim.log.levels.ERROR
    vim.notify(msg, level, { title = "./run" })
  end)
end

-- Terminal flutuante: { buf, win, show(), hide() }.
local Term = {}
Term.__index = Term

function Term:show()
  if self.win and vim.api.nvim_win_is_valid(self.win) then
    vim.api.nvim_set_current_win(self.win)
    return self
  end
  local width = math.floor(vim.o.columns * 0.85)
  local height = math.floor((vim.o.lines - 2) * 0.8)
  self.win = vim.api.nvim_open_win(self.buf, true, {
    relative = "editor",
    width = width,
    height = height,
    row = math.floor((vim.o.lines - 2 - height) / 2),
    col = math.floor((vim.o.columns - width) / 2),
    border = "rounded",
    title = self.title,
    title_pos = "center",
    style = "minimal",
  })
  return self
end

function Term:hide()
  if self.win and vim.api.nvim_win_is_valid(self.win) then
    vim.api.nvim_win_close(self.win, true)
  end
  self.win = nil
end

local function open_terminal(cmd, root, args)
  local term = setmetatable({
    buf = vim.api.nvim_create_buf(false, true),
    title = " ./run " .. key(args) .. "  (q esconde) ",
  }, Term)
  vim.bo[term.buf].bufhidden = "hide"
  term:show()
  vim.api.nvim_buf_call(term.buf, function()
    vim.fn.jobstart(cmd, {
      cwd = root,
      term = true,
      on_exit = function(_, code)
        on_exit(args, code)
      end,
    })
  end)
  vim.keymap.set("n", "q", function()
    term:hide()
  end, { buffer = term.buf, desc = "Esconde o terminal do ./run" })
  return term
end

-- Roda "./run <args...>" na raiz do projeto. Devolve o terminal
-- (com .buf) ou nil. opts.confirm: pergunta antes (down, db:reset).
function M.run(args, opts)
  opts = opts or {}
  local root = fw.context()
  if not root then
    return nil
  end
  if vim.fn.executable(root .. "/run") ~= 1 then
    fw.notify("não achei o ./run executável em " .. root, vim.log.levels.ERROR)
    return nil
  end
  if opts.confirm then
    local question = ("Rodar ./run %s?"):format(key(args))
    local ans = vim.fn.confirm(question, "&Sim\n&Não", 2)
    if ans ~= 1 then
      return nil
    end
  end
  M.last = args
  local cmd = vim.list_extend({ "./run" }, args)
  local k = key(args)
  local old = terms[k]
  if old and vim.api.nvim_buf_is_valid(old.buf) then
    local job = vim.b[old.buf].terminal_job_id
    if job and vim.fn.jobwait({ job }, 0)[1] == -1 then
      return old:show()
    end
    old:hide()
    pcall(vim.api.nvim_buf_delete, old.buf, { force = true })
  end
  terms[k] = open_terminal(cmd, root, args)
  return terms[k]
end

-- Repete o último comando.
function M.last_run()
  if not M.last then
    return fw.notify("nenhum ./run nesta sessão ainda")
  end
  M.run(M.last)
end

-- Teste do arquivo atual: Cest -> test:browser; *Test.php -> test;
-- código com teste correspondente -> esse teste.
function M.current_file_args()
  local root, path, rel = fw.context()
  if not (root and rel) then
    return nil
  end
  if rel:match("^tests/Acceptance/.+Cest%.php$") then
    return { "test:browser", rel }
  elseif rel:match("^tests/.+Test%.php$") then
    return { "test", rel }
  end
  local test = fw.test_for(root, rel)
  if test then
    return { "test", fw.rel(test, root) }
  end
  local name = vim.fn.fnamemodify(path or rel, ":t")
  fw.notify("sem teste para " .. name, vim.log.levels.WARN)
  return nil
end

function M.test_file()
  local args = M.current_file_args()
  if args then
    M.run(args)
  end
end

-- Só o teste (método) sob o cursor.
function M.test_nearest()
  local _, _, rel = fw.context()
  if not rel then
    return
  end
  local name = fw.current_method(0)
  local cest = rel:match("^tests/Acceptance/.+Cest%.php$")
  if cest and name and not name:match("^_") then
    return M.run({ "test:browser", rel .. ":" .. name })
  elseif rel:match("^tests/.+Test%.php$") and name and name:match("^test") then
    return M.run({ "test", "--filter", name, rel })
  end
  fw.notify(
    "ponha o cursor dentro de um método test_... ou de um Cest",
    vim.log.levels.WARN
  )
end

-- Nomes das funções do ./run (para completar o :Run).
function M.complete(arglead)
  local root = fw.root()
  local out = {}
  if root then
    local ok, lines = pcall(vim.fn.readfile, root .. "/run")
    for _, line in ipairs(ok and lines or {}) do
      local name = line:match("^function%s+([%w:_%-]+)")
      if name and name:sub(1, #arglead) == arglead then
        table.insert(out, name)
      end
    end
  end
  return out
end

-- Tarefas com atalho (ver lua/config/keymaps.lua).
M.tasks = {
  unit = { "test", "tests/Unit" },
  integration = { "test", "tests/Integration" },
  browser = { "test:browser" },
  all = { "test" },
  phpcs = { "phpcs" },
  phpcbf = { "phpcbf" },
  phpstan = { "phpstan" },
  populate = { "db:populate" },
  reset = { "db:reset" },
  up = { "up", "-d" },
  down = { "down" },
  ps = { "ps" },
}

function M.task(name, opts)
  return function()
    M.run(M.tasks[name], opts)
  end
end

return M

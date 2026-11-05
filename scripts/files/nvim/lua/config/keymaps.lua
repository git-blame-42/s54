-- Atalhos da disciplina, além dos do LazyVim (lazyvim/config/keymaps.lua).
-- A tecla líder é o Espaço. Todos em modo normal. Os grupos usados
-- (m, j, t, p, r) não existem no LazyVim padrão; o <Espaço>? do
-- LazyVim (atalhos do buffer) virou a referência rápida da disciplina:
-- para listar atalhos use <Espaço>sk.

-- nmap(atalho, função, descrição)
local function nmap(lhs, rhs, desc)
  vim.keymap.set("n", lhs, rhs, { desc = desc })
end

-- Função de um módulo lua/s50/<mod>.lua, chamada com os argumentos.
local function call(mod, fn, ...)
  local args = { ... }
  return function()
    require("s50." .. mod)[fn](unpack(args))
  end
end
local function fw(fn, ...)
  return call("framework", fn, ...)
end
local function run(name, opts)
  return function()
    require("s50.run").task(name, opts)()
  end
end

-- Referência rápida
nmap("<leader>?", call("ajuda", "dicas"), "Dicas do Neovim (neovim-dicas.md)")

-- <Espaço>m: navegação MVC do framework
nmap("<leader>ma", fw("alternate"),
  "Alternar (controller/view, código/teste, rota)")
nmap("<leader>mv", fw("goto_view"), "View da ação sob o cursor")
nmap("<leader>mc", fw("goto_controller"), "Controller (e ação) desta view")
nmap("<leader>mt", fw("goto_test"),
  "Código <-> teste (model, controller, lib)")
nmap("<leader>mr", fw("goto_route"),
  "Rota <-> ação (ou route('nome') sob o cursor)")
nmap("<leader>mo", fw("goto_model"), "Model do recurso")

-- <Espaço>j: arquivos e pastas do projeto (minúscula: arquivo por
-- nome; maiúscula: busca texto dentro da pasta). vendor/ fica fora.
nmap("<leader>ja", fw("pick", "app"), "Arquivos de app/")
nmap("<leader>jA", fw("pick", "app", true), "Buscar texto em app/")
nmap("<leader>jv", fw("pick", "app/views"), "Arquivos de app/views/")
nmap("<leader>jV", fw("pick", "app/views", true),
  "Buscar texto em app/views/")
nmap("<leader>jc", fw("pick", "core"), "Arquivos de core/")
nmap("<leader>jC", fw("pick", "core", true), "Buscar texto em core/")
nmap("<leader>jl", fw("pick", "lib"), "Arquivos de lib/")
nmap("<leader>jL", fw("pick", "lib", true), "Buscar texto em lib/")
nmap("<leader>jt", fw("pick", "tests"), "Arquivos de tests/")
nmap("<leader>jT", fw("pick", "tests", true), "Buscar texto em tests/")
nmap("<leader>jr", fw("edit", "config/routes.php"),
  "Abrir config/routes.php")
nmap("<leader>js", fw("edit", "database/schema.sql"),
  "Abrir database/schema.sql")
nmap("<leader>jf", call("ajuda", "fvp"),
  "FVP: cenários 4.2 (fvp.sh -c 4.2)")

-- <Espaço>t: testes pelo ./run (terminal flutuante; q esconde)
nmap("<leader>tu", run("unit"), "./run test tests/Unit")
nmap("<leader>ti", run("integration"), "./run test tests/Integration")
nmap("<leader>tb", run("browser"), "./run test:browser (aceitação)")
nmap("<leader>ta", run("all"), "./run test (todos do phpunit)")
nmap("<leader>tf", call("run", "test_file"), "Testes do arquivo atual")
nmap("<leader>tn", call("run", "test_nearest"), "Teste sob o cursor")
nmap("<leader>tl", call("run", "last_run"), "Repetir o último ./run")

-- <Espaço>p: qualidade do PHP
nmap("<leader>pc", run("phpcs"), "./run phpcs (projeto todo)")
nmap("<leader>pf", run("phpcbf"), "./run phpcbf (corrige o projeto todo)")
nmap("<leader>ps", run("phpstan"), "./run phpstan")
nmap("<leader>pl", call("lint", "lint_current"),
  "phpcs + phpstan do arquivo (diagnósticos)")

-- <Espaço>r: stack do ./run
nmap("<leader>ru", run("up"), "./run up -d (sobe a stack)")
nmap("<leader>rd", run("down", { confirm = true }),
  "./run down (derruba a stack)")
nmap("<leader>rp", run("populate"), "./run db:populate")
nmap("<leader>rr", run("reset", { confirm = true }),
  "./run db:reset (recria o banco)")
nmap("<leader>rs", run("ps"), "./run ps (containers)")
nmap("<leader>rc", ":Run ", "./run <comando> (digite)")

-- Comandos ":" equivalentes
local cmd = vim.api.nvim_create_user_command
cmd("FwAlt", fw("alternate"),
  { desc = "Alternar controller/view, código/teste, rota" })
cmd("FwView", fw("goto_view"), { desc = "View da ação sob o cursor" })
cmd("FwController", fw("goto_controller"), { desc = "Controller desta view" })
cmd("FwTest", fw("goto_test"), { desc = "Código <-> teste" })
cmd("FwRoute", fw("goto_route"), { desc = "Rota <-> ação" })
cmd("FwModel", fw("goto_model"), { desc = "Model do recurso" })
cmd("Run", function(o)
  require("s50.run").run(o.fargs)
end, {
  nargs = "+",
  desc = "./run <args> no terminal flutuante",
  complete = function(arglead)
    return require("s50.run").complete(arglead)
  end,
})
cmd("PhpLint", call("lint", "lint_current"),
  { desc = "phpcs + phpstan do arquivo" })
cmd("Dicas", call("ajuda", "dicas"), { desc = "Abre o neovim-dicas.md" })
cmd("Fvp", function(o)
  require("s50.ajuda").fvp(o.fargs)
end, { nargs = "*", desc = "fvp.sh <args> num buffer" })

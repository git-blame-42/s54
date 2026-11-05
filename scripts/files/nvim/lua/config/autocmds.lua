-- Autocomandos carregados pelo LazyVim no evento VeryLazy (os padrões
-- do LazyVim estão em lazyvim/config/autocmds.lua).
local group = vim.api.nvim_create_augroup("s50_tsi34d", { clear = true })

-- PHP e .phtml: 4 espaços, como o código do projeto e o PSR-12 do
-- phpcs.xml. (O pedido inicial falava em 2, que é o do .editorconfig
-- do projeto; com 2, o "./run phpcs" reprova toda linha indentada.)
-- A linha vertical fica na coluna 131: a primeira que passa do limite
-- de 130 caracteres do phpcs.xml (Generic.Files.LineLength).
vim.api.nvim_create_autocmd("FileType", {
  group = group,
  pattern = "php",
  callback = function(ev)
    local bo, name = vim.bo[ev.buf], vim.api.nvim_buf_get_name(ev.buf)
    bo.expandtab = true
    bo.shiftwidth = 4
    bo.tabstop = 4
    bo.softtabstop = 4
    vim.wo.colorcolumn = "131"
    local fw = require("s50.framework")
    -- Formatar ao salvar só com o phpcbf nativo (mesmas regras do
    -- "./run phpcs"). Sem ele, ou em .phtml (o phpcs do projeto só
    -- olha .php), não formata sozinho: <Espaço>cf formata à mão.
    if name:match("%.phtml$") or not fw.native_tool(ev.buf, "phpcbf") then
      vim.b[ev.buf].autoformat = false
    end
  end,
})

-- Markdown (docs, wiki, README): linha vertical na coluna 81, a
-- primeira depois das 80 usadas nos textos da disciplina.
vim.api.nvim_create_autocmd("FileType", {
  group = group,
  pattern = { "markdown", "gitcommit" },
  callback = function()
    vim.wo.colorcolumn = "81"
  end,
})

-- Diagnósticos de phpcs e phpstan (lua/s50/lint.lua). Só com as
-- ferramentas nativas: phpcs ao abrir, ao sair da inserção e ao
-- salvar; phpstan (mais lento) só ao salvar. Sem PHP nativo nada roda
-- sozinho; <Espaço>pl chama a versão pelo container, sob demanda.
vim.api.nvim_create_autocmd({ "BufReadPost", "InsertLeave", "BufWritePost" }, {
  group = group,
  pattern = "*.php",
  callback = function(ev)
    local lint = require("s50.lint")
    lint.auto(ev.buf, "phpcs")
    if ev.event == "BufWritePost" then
      lint.auto(ev.buf, "phpstan")
    end
  end,
})

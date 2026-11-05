-- Opções carregadas pelo LazyVim antes dos plugins. Os padrões do
-- LazyVim estão em lazyvim/config/options.lua; aqui só o que muda para
-- o framework da disciplina.
local opt = vim.opt

-- Raiz do projeto: a pasta com composer.json E o script ./run (a raiz
-- do framework). Assim um arquivo aberto dentro de vendor/<pacote>/,
-- que também tem composer.json, continua no projeto. Sem isso, cai
-- nas regras normais do LazyVim (LSP, .git, pasta atual).
vim.g.root_spec = {
  function(buf)
    return require("s50.framework").root(buf)
  end,
  "lsp",
  { ".git", "lua" },
  "cwd",
}

-- Clipboard do sistema: "y" copia e "p" cola de/para o Ctrl+C/Ctrl+V
-- do Ubuntu (o 10 instala xclip e wl-clipboard). Por SSH fica
-- desligado, como no LazyVim, porque não há clipboard para usar.
opt.clipboard = vim.env.SSH_CONNECTION and "" or "unnamedplus"

-- Indentação padrão: 2 espaços (lua, yaml, json, markdown, sh...).
-- PHP e .phtml usam 4 (PSR-12): ver lua/config/autocmds.lua.
opt.expandtab = true
opt.shiftwidth = 2
opt.tabstop = 2
opt.softtabstop = 2

-- Tipos de arquivo do projeto.
vim.filetype.add({
  extension = {
    -- Views do framework: PHP com HTML. O parser treesitter de php
    -- injeta html no texto fora de <?php ?>, então as duas partes
    -- ficam coloridas e o intelephense funciona dentro do <?php ?>.
    phtml = "php",
    neon = "yaml", -- phpstan.neon
  },
  filename = {
    [".env"] = "sh",
    [".env.example"] = "sh",
    ["phpunit.xml"] = "xml",
    ["phpunit.xml.dist"] = "xml",
    ["phpcs.xml"] = "xml",
    ["phpcs.xml.dist"] = "xml",
    ["codeception.yml"] = "yaml",
    ["docker-compose.yml"] = "yaml",
    ["docker-compose.override.yml"] = "yaml",
    ["compose.yml"] = "yaml",
  },
  pattern = {
    ["%.env%.[%w_.-]+"] = "sh",
    [".*/tests/.*%.suite%.yml"] = "yaml",
    ["docker%-compose%..*%.ya?ml"] = "yaml",
    -- ./run (sem extensão) é bash
    [".*/run"] = {
      function(path)
        local f = io.open(path, "r")
        local first = f and f:read("*l") or ""
        if f then
          f:close()
        end
        return first:match("^#!.*bash") and "bash" or nil
      end,
      { priority = -1 },
    },
  },
})

-- O .editorconfig do projeto pede indent_size = 2 para todos os
-- arquivos, mas o código PHP (e o phpcs.xml, PSR-12) usa 4. O Neovim
-- aplica o .editorconfig sozinho; aqui ele deixa de mexer na
-- indentação de .php e .phtml. O resto do .editorconfig (charset,
-- fim de linha, espaço no fim da linha) continua valendo.
--
-- O mesmo .editorconfig tem comentários no fim das linhas
-- ("end_of_line = lf # ..."), que o Neovim não aceita: sem o ajuste
-- abaixo, cada arquivo aberto mostraria três erros "invalid value".
-- Aqui o comentário é cortado antes de aplicar o valor.
local ok, editorconfig = pcall(require, "editorconfig")
if ok and type(editorconfig.properties) == "table" then
  local indent = { indent_style = true, indent_size = true, tab_width = true }
  for prop, orig in pairs(editorconfig.properties) do
    editorconfig.properties[prop] = function(bufnr, val, opts)
      if type(val) == "string" then
        val = vim.trim((val:gsub("%s+[#;].*$", "")))
      end
      local name = vim.api.nvim_buf_get_name(bufnr)
      if indent[prop] and (name:match("%.php$") or name:match("%.phtml$")) then
        return
      end
      return orig(bufnr, val, opts)
    end
  end
end

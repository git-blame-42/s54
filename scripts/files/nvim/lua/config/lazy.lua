-- Carrega o lazy.nvim (gerenciador de plugins), o LazyVim, o extra de
-- PHP e os plugins de lua/plugins/. As versões ficam presas no
-- lazy-lock.json ao lado do init.lua: o 10-install-neovim.sh roda
-- ":Lazy! restore", que instala exatamente esses commits.

-- PHP com intelephense. O phpactor (padrão do extra) falhou em
-- ir-para-definição e autocompletar no teste da disciplina. Precisa
-- vir antes do import do extra, que lê esta variável.
vim.g.lazyvim_php_lsp = "intelephense"

local lazypath = vim.fn.stdpath("data") .. "/lazy/lazy.nvim"
if not (vim.uv or vim.loop).fs_stat(lazypath) then
  local lazyrepo = "https://github.com/folke/lazy.nvim.git"
  local out = vim.fn.system({
    "git", "clone", "--filter=blob:none", "--branch=stable",
    lazyrepo, lazypath,
  })
  if vim.v.shell_error ~= 0 then
    vim.api.nvim_echo({
      { "Falhou ao clonar o lazy.nvim:\n", "ErrorMsg" },
      { out, "WarningMsg" },
      { "\nAperte uma tecla para sair..." },
    }, true, {})
    vim.fn.getchar()
    os.exit(1)
  end
end
vim.opt.rtp:prepend(lazypath)

require("lazy").setup({
  spec = {
    { "LazyVim/LazyVim", import = "lazyvim.plugins" },
    -- Extra de PHP do LazyVim: treesitter php, intelephense, conform e
    -- nvim-lint. lua/plugins/php.lua ajusta tudo ao projeto.
    { import = "lazyvim.plugins.extras.lang.php" },
    { import = "plugins" },
  },
  defaults = {
    lazy = false,
    version = false,
  },
  install = { colorscheme = { "tokyonight", "habamax" } },
  -- Sem checagem periódica de atualização: a máquina do laboratório
  -- fica com as versões do lazy-lock.json. Para atualizar: :Lazy update
  checker = { enabled = false },
  change_detection = { notify = false },
  performance = {
    rtp = {
      -- O starter do LazyVim desliga também o "tutor"; aqui ele fica,
      -- porque o neovim-dicas.md recomenda o :Tutor.
      disabled_plugins = {
        "gzip",
        "tarPlugin",
        "tohtml",
        "zipPlugin",
      },
    },
  },
})

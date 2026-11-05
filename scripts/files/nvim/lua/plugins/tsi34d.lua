-- Ajustes dos plugins do LazyVim para o framework da disciplina e o
-- projeto Eventos do Câmpus.
local function root(bufnr)
  return require("s50.framework").lsp_root(bufnr)
end

-- Pastas que não entram na busca de arquivos nem de texto. O vendor/
-- continua indexado pelo intelephense (gd entra nas classes dele).
local exclude = { "vendor", ".cache", "log" }
-- O mesmo, em padrões Lua, para o telescope e o fzf-lua.
local ignore = { "^vendor/", "/vendor/", "^%.cache/", "^log/" }

return {
  -- Treesitter: php (com html injetado nas .phtml), phpdoc, sql do
  -- schema.sql, css/js das views, dockerfile. A indentação do PHP fica
  -- com o treesitter (padrão do LazyVim): no teste, "gg=G" não mudou
  -- nenhuma linha de controllers, models, testes, routes.php e views;
  -- o indent/php.vim do Neovim mudou 50 linhas do _form.phtml.
  {
    "nvim-treesitter/nvim-treesitter",
    opts = {
      ensure_installed = {
        "php", "php_only", "phpdoc", "html", "css", "javascript",
        "sql", "dockerfile", "xml", "yaml", "json", "markdown", "bash",
      },
    },
  },

  -- intelephense: raiz única do projeto (um arquivo de vendor/ aberto
  -- com gd usa o mesmo servidor), PHP 8.3 (o do Dockerfile).
  {
    "neovim/nvim-lspconfig",
    opts = {
      servers = {
        intelephense = {
          root_dir = function(bufnr, on_dir)
            on_dir(root(bufnr))
          end,
          settings = {
            intelephense = {
              environment = { phpVersion = "8.3.4" },
              files = {
                maxSize = 5000000,
                -- padrão do intelephense + .cache/ do composer
                exclude = {
                  "**/.git/**", "**/.svn/**", "**/.hg/**", "**/CVS/**",
                  "**/.DS_Store/**", "**/node_modules/**",
                  "**/bower_components/**", "**/vendor/**/{Tests,tests}/**",
                  "**/.history/**", "**/vendor/**/vendor/**", "**/.cache/**",
                },
              },
            },
          },
        },
      },
    },
  },

  -- O extra de PHP pede phpcs e php-cs-fixer ao mason. Aqui não: o
  -- phpcs é o do projeto (vendor/bin) ou o do 09, e o php-cs-fixer
  -- usa regras diferentes das do phpcs.xml.
  {
    "mason-org/mason.nvim",
    opts = function(_, opts)
      opts.ensure_installed = vim.tbl_filter(function(tool)
        return tool ~= "phpcs" and tool ~= "php-cs-fixer"
      end, opts.ensure_installed or {})
    end,
  },

  -- Formatar PHP com o phpcbf (regras do phpcs.xml do projeto), nunca
  -- com o php-cs-fixer. Só com phpcbf nativo; ver lua/config/autocmds.lua.
  {
    "stevearc/conform.nvim",
    opts = {
      formatters_by_ft = { php = { "phpcbf" } },
      formatters = {
        phpcbf = {
          command = function(_, ctx)
            local fw = require("s50.framework")
            return fw.native_tool(ctx.buf, "phpcbf") or "phpcbf"
          end,
          cwd = function(_, ctx)
            return require("s50.framework").root(ctx.buf)
          end,
          condition = function(_, ctx)
            return ctx.filename:match("%.php$") ~= nil
              and require("s50.framework").native_tool(ctx.buf, "phpcbf") ~= nil
          end,
        },
      },
    },
  },

  -- O nvim-lint do LazyVim rodaria o phpcs na pasta atual do Neovim;
  -- o lua/s50/lint.lua roda phpcs e phpstan na raiz do projeto.
  {
    "mfussenegger/nvim-lint",
    opts = function(_, opts)
      opts.linters_by_ft = opts.linters_by_ft or {}
      opts.linters_by_ft.php = nil
    end,
  },

  -- Busca (snacks picker, o padrão do LazyVim): vendor/ fora.
  {
    "folke/snacks.nvim",
    opts = {
      picker = {
        sources = {
          files = { exclude = exclude },
          grep = { exclude = exclude },
          grep_word = { exclude = exclude },
        },
      },
    },
  },
  -- Mesma coisa se o extra do telescope ou do fzf-lua for ligado.
  {
    "nvim-telescope/telescope.nvim",
    optional = true,
    opts = { defaults = { file_ignore_patterns = ignore } },
  },
  {
    "ibhagwan/fzf-lua",
    optional = true,
    opts = { defaults = { file_ignore_patterns = ignore } },
  },

  -- Snippets do framework em snippets/php.json (blink.cmp, o
  -- autocompletar do LazyVim, lê a pasta snippets/ da config).
  -- Digite "fw" num .php ou .phtml para ver a lista.

  -- which-key: nomes dos grupos novos; o <Espaço>? do LazyVim fica
  -- para a referência rápida (lua/config/keymaps.lua).
  {
    "folke/which-key.nvim",
    keys = { { "<leader>?", false } },
    opts = {
      spec = {
        { "<leader>m", group = "mvc (framework)" },
        { "<leader>j", group = "projeto (pastas)" },
        { "<leader>t", group = "testes (./run)" },
        { "<leader>p", group = "php: phpcs/phpstan" },
        { "<leader>r", group = "./run (stack)" },
      },
    },
  },
}

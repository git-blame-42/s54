-- Usado pelo scripts/10-install-neovim.sh em modo headless, para a
-- primeira abertura não ficar esperando downloads e compilação:
--   nvim --headless "+lua require('s50.setup').treesitter()" +qa
-- Sai com :cquit (código 1) se faltar algum parser no fim.
local M = {}

function M.treesitter(timeout)
  timeout = timeout or 600000
  require("lazy").load({ plugins = { "nvim-treesitter" } })
  local ok, ts = pcall(require, "nvim-treesitter")
  if not (ok and ts.get_installed) then
    io.stderr:write("nvim-treesitter (branch main) indisponível\n")
    return vim.cmd("cquit 1")
  end
  local want = LazyVim.opts("nvim-treesitter").ensure_installed or {}
  local function missing()
    local have = {}
    for _, lang in ipairs(ts.get_installed()) do
      have[lang] = true
    end
    return vim.tbl_filter(function(lang)
      return not have[lang]
    end, want)
  end
  -- A config do LazyVim já disparou a instalação (e, se faltar, a do
  -- tree-sitter-cli pelo mason); aqui só espera terminar.
  vim.wait(timeout, function()
    return #missing() == 0
  end, 2000)
  local left = missing()
  if #left > 0 then
    io.stderr:write("parsers faltando: " .. table.concat(left, " ") .. "\n")
    return vim.cmd("cquit 1")
  end
  io.stdout:write(("parsers treesitter: %d instalados\n"):format(#want))
end

return M

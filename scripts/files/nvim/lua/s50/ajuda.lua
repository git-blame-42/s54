-- Referência rápida: <Espaço>? abre o neovim-dicas.md do repositório
-- s50; :Fvp roda o fvp.sh do mesmo repositório e mostra a saída.
--
-- O caminho do repositório vem do arquivo s50-repo, que o
-- scripts/10-install-neovim.sh grava ao lado do init.lua (a config é
-- copiada, então não dá para deduzir de onde ela está). Sem ele, tenta
-- ~/s50, o lugar padrão no laboratório.
local M = {}

local uv = vim.uv or vim.loop

function M.repo()
  local candidates = {}
  local file = vim.fn.stdpath("config") .. "/s50-repo"
  local ok, lines = pcall(vim.fn.readfile, file)
  if ok and lines[1] and lines[1] ~= "" then
    table.insert(candidates, vim.trim(lines[1]))
  end
  table.insert(candidates, vim.fn.expand("~/s50"))
  for _, dir in ipairs(candidates) do
    if uv.fs_stat(dir .. "/neovim-dicas.md") then
      return dir
    end
  end
  return nil
end

local function float(buf, title)
  if _G.Snacks and Snacks.win then
    return Snacks.win({
      buf = buf,
      width = 0.8,
      height = 0.85,
      border = "rounded",
      title = " " .. title .. "  (q fecha) ",
      title_pos = "center",
      wo = { wrap = true, conceallevel = 2, spell = false },
      keys = { q = "close" },
    })
  end
  vim.cmd("tab sbuffer " .. buf)
  vim.keymap.set("n", "q", "<cmd>close<cr>", { buffer = buf })
end

function M.dicas()
  local repo = M.repo()
  if not repo then
    return vim.notify(
      "não achei o neovim-dicas.md (repositório s50 em ~/s50?)",
      vim.log.levels.WARN,
      { title = "ajuda" }
    )
  end
  local path = repo .. "/neovim-dicas.md"
  local buf = vim.fn.bufadd(path)
  vim.fn.bufload(buf)
  vim.bo[buf].modifiable = false
  vim.bo[buf].readonly = true
  vim.bo[buf].buflisted = false
  vim.bo[buf].filetype = "markdown"
  float(buf, "neovim-dicas.md")
end

-- :Fvp [args]: saída do fvp.sh num buffer (padrão: -c 4.2, a lista
-- de cenários por chance).
function M.fvp(args)
  local repo = M.repo()
  if not (repo and uv.fs_stat(repo .. "/fvp.sh")) then
    return vim.notify(
      "não achei o fvp.sh (repositório s50)",
      vim.log.levels.WARN,
      { title = "ajuda" }
    )
  end
  if not args or #args == 0 then
    args = { "-c", "4.2" }
  end
  local cmd = vim.list_extend({ "bash", repo .. "/fvp.sh" }, args)
  local res = vim.system(cmd, { text = true }):wait()
  local out = (res.stdout or "") .. (res.stderr or "")
  out = out:gsub("\27%[[%d;]*m", "") -- tira cores
  local buf = vim.api.nvim_create_buf(false, true)
  local lines = vim.split(out, "\n", { plain = true })
  vim.api.nvim_buf_set_lines(buf, 0, -1, false, lines)
  vim.bo[buf].modifiable = false
  vim.bo[buf].bufhidden = "wipe"
  vim.bo[buf].filetype = "markdown"
  float(buf, "fvp.sh " .. table.concat(args, " "))
  return buf
end

return M

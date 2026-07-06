# Smoke Tests Playground Rules

- `GET /tests`, `GET /tests/index.json` e `GET /tests/api` retornam o mesmo JSON de índice.
- Não existe UI Twig neste pacote.
- O frontend humano fica no projeto separado `tests-frontend-tool`.
- O índice público é montado a partir de `var/tests/browser-smoke/<suite>/report.json`.
- O diretório padrão de smoke tests é `var/tests/browser-smoke`.
- Não exponha caminhos absolutos como `testsPath`, `reportPath` ou `runWorkingDirectory` na API pública.
- Prints e vídeos devem ser servidos por URLs autenticadas em `/tests/artifacts/{suite}/{arquivo}`.
- A API pública deve expor `status`, `progress`, `message`, `lastRunAt`, `summary` e `suites`.
- Cada suite publica seus `tests[]`, `steps[]` e `screenshots[]` já normalizados.
- Quando alterar o contrato público, atualize a API, o README e o frontend consumidor no mesmo ajuste.
- O instalador deve escrever `PLAYWRIGHT_BROWSERS_PATH="0"` no `.env`.
- O comando padrão de run deve usar `node node_modules/@playwright/test/cli.js`, sem `npx`.
- O instalador deve continuar sendo o ponto de entrada para escrever defaults no projeto consumidor.

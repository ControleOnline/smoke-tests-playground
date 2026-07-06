# Smoke Tests Playground Rules

- `GET /tests` é a UI HTML de conferência renderizada por Twig.
- `GET /tests/api` e `POST /tests/run` são a API pública da lib e sempre devem responder JSON.
- A UI deve consumir a API por `fetch`; não leia `report.json` direto no HTML.
- Mantenha controller fino: regras de montagem de payload ficam em services, e o HTML em `templates/smoke_tests_playground/`.
- O backend da lib não deve hardcodar paths absolutos do workspace; use `SMOKE_TESTS_PLAYGROUND_TESTS_PATH` e os outros valores do `.env`.
- O valor padrão de `SMOKE_TESTS_PLAYGROUND_TESTS_PATH` deve apontar para `var/tests/browser-smoke/company-advertiser-route`.
- O valor padrão de `SMOKE_TESTS_PLAYGROUND_RUN_COMMAND` deve usar `node node_modules/@playwright/test/cli.js`, sem depender de `npx` nem de prefixo inline de variável de ambiente.
- O instalador deve escrever `PLAYWRIGHT_BROWSERS_PATH=0` no `.env` para evitar depender do cache do usuário do sistema.
- A API pública não deve expor `testsPath`, `reportPath`, `runCommand`, `runWorkingDirectory`, `runTimeout` nem o `report` bruto; exponha `status`, `progress`, `message`, `lastRunAt`, `summary` e `tests` sanitizados com prints em base64.
- Quando alterar o contrato público, atualize a API, a página HTML e o `README.md` juntos.
- O comando de instalação deve continuar sendo o ponto de entrada para escrever defaults no projeto consumidor.
- O comando de instalação deve tentar baixar os browsers automaticamente e, se não conseguir por permissão, imprimir os comandos de `root` necessários para corrigir o host.
- A documentação do pacote não deve citar ambientes ou marcas específicas do consumidor; use placeholders como `<your-host>` e `<basic-auth-user>`.

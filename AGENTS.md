# Smoke Tests Playground Rules

- `GET /tests` é a UI HTML de conferência renderizada por Twig.
- `GET /tests/api` e `POST /tests/run` são a API pública da lib e sempre devem responder JSON.
- A UI deve consumir a API por `fetch`; não leia `report.json` direto no HTML.
- Mantenha controller fino: regras de montagem de payload ficam em services, e o HTML em `templates/smoke_tests_playground/`.
- O backend da lib não deve hardcodar paths absolutos do workspace; use `SMOKE_TESTS_PLAYGROUND_TESTS_PATH` e os outros valores do `.env`.
- O valor padrão de `SMOKE_TESTS_PLAYGROUND_TESTS_PATH` deve apontar para `var/tests/browser-smoke/transporter-login`.
- A API pública não deve expor `testsPath`, `reportPath`, `runCommand`, `runWorkingDirectory`, `runTimeout`, `report` nem screenshots; exponha só estado, progresso, mensagem e resultado operacional.
- Quando alterar o contrato público, atualize a API, a página HTML e o `README.md` juntos.
- O comando de instalação deve continuar sendo o ponto de entrada para escrever defaults no projeto consumidor.

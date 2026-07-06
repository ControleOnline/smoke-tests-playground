# Smoke Tests Playground Rules

- `GET /tests` e `POST /tests/run` são a API pública da lib e sempre devem responder JSON.
- A página HTML de conferência deve viver em uma rota separada, hoje `GET /tests/ui`, e consumir a API por `fetch`; não leia `report.json` direto no HTML.
- O backend da lib não deve hardcodar paths absolutos do workspace; use `SMOKE_TESTS_PLAYGROUND_TESTS_PATH` e os outros valores do `.env`.
- O valor padrão de `SMOKE_TESTS_PLAYGROUND_TESTS_PATH` deve apontar para `var/tests/browser-smoke/transporter-login`.
- O relatório publicado precisa continuar compatível com a tela: manter `status`, `generatedAt`, `suite`, `testsPath`, `reportPath`, `runCommand`, `runWorkingDirectory`, `runTimeout` e `report`.
- Screenshots e `report.json` devem ficar no mesmo diretório configurado pelo smoke.
- Quando alterar o formato do payload, atualize a API, a página HTML e o `README.md` juntos.
- O comando de instalação deve continuar sendo o ponto de entrada para escrever defaults no projeto consumidor.

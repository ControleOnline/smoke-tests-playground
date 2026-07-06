# Smoke Tests Playground

Bundle Symfony para expor a UI de conferência do último smoke test e os endpoints de API para consultar e disparar execuções.
A interface humana fica em `GET /tests`, é renderizada por Twig e consome a API em `GET /tests/api` e `POST /tests/run`.

## Instalação

1. Se o servidor ainda não tiver Node.js, instale o `nvm` e carregue a sessão:

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
source ~/.bashrc
```

2. Instale uma versão do Node.js e carregue o ambiente:

```bash
nvm install --lts
nvm use --lts
```

3. Instale o Playwright no projeto consumidor. O bootstrap do pacote tenta baixar os browsers sozinho, mas este comando continua útil para reinstalação manual:

```bash
npm install -D @playwright/test
npm run test:browser:install
```

4. Instale o pacote com Composer.
5. Registre o bundle no `config/bundles.php` do projeto consumidor:

```php
ControleOnline\SmokeTestsPlayground\SmokeTestsPlaygroundBundle::class => ['all' => true],
```

6. Execute o comando de bootstrap:

```bash
php bin/console smoke-tests-playground:install
```

Se houver problema de permissão, o próprio comando imprime os comandos que precisam ser executados como `root`.

O comando cria:

- `.env` com os defaults do smoke
- `config/routes/smoke_tests_playground.yaml`
- `config/services/smoke_tests_playground.yaml`

## Variáveis de ambiente

- `SMOKE_TESTS_PLAYGROUND_TESTS_PATH`: diretório onde ficam os artifacts e o `report.json`
- `SMOKE_TESTS_PLAYGROUND_RUN_COMMAND`: comando usado pelo botão "Rodar novos testes" e pelo endpoint `POST /tests/run`
- `SMOKE_TESTS_PLAYGROUND_RUN_WORKDIR`: diretório de execução do comando
- `SMOKE_TESTS_PLAYGROUND_RUN_TIMEOUT`: timeout em segundos

O comando padrão assume Playwright instalado localmente no projeto consumidor:

```bash
node node_modules/@playwright/test/cli.js test --config=playwright.config.cjs tests/browser/company-advertiser-route-smoke.spec.js
```

Se precisar sobrescrever o comando, mantenha `PLAYWRIGHT_BROWSERS_PATH="0"` separado no ambiente. O runner da lib usa `node node_modules/@playwright/test/cli.js` e não depende de prefixo inline no comando.

## Rotas

- `GET /tests` - UI HTML
- `GET /tests/api` - JSON público com status, progresso e mensagem
- `POST /tests/run` - JSON público com o resultado da execução
- `GET /tests/ui` - alias da UI para compatibilidade

## Respostas

`GET /tests/api` devolve um JSON público com:

- `status`
- `progress`
- `message`
- `lastRunAt`
- `summary.total`
- `summary.passed`
- `summary.failed`
- `tests[]` com nome, status, erro e prints sanitizados

`POST /tests/run` executa o comando configurado, devolve o mesmo payload de `GET /tests/api` e adiciona:

- `run.successful`
- `run.message`
- `run.requestedAt`
- `requestedMethod`

`GET /tests` entrega uma página HTML renderizada por Twig que consome `GET /tests/api` e `POST /tests/run` via `fetch`.
A tela mostra o status geral, a lista de testes e os prints de cada caso publicado no último relatório.

## Estrutura

- `src/Controller/SmokeTestsController.php` mantém só as rotas
- `src/Service/SmokeTestsPublicStateFactory.php` monta os payloads públicos JSON
- `src/Service/SmokeTestsPageRenderer.php` e `src/Service/SmokeTestsPageContextFactory.php` cuidam da UI
- `templates/smoke_tests_playground/` contém o HTML, os estilos e o JavaScript separados

## Formato esperado do relatório

O controller lê `report.json` dentro do diretório configurado em `SMOKE_TESTS_PLAYGROUND_TESTS_PATH`, mas o dado bruto não é exposto na API pública.
O relatório continua sendo o arquivo interno usado para calcular o status público da tela.

## Conferência manual

Depois de instalar o pacote no projeto consumidor e publicar a UI, valide o smoke run com um comando simples.

`GET /tests/api` consulta o último estado público:

```bash
curl -u "<basic-auth-user>:<basic-auth-pass>" \
  -H "Accept: application/json" \
  "https://<your-host>/tests/api"
```

`POST /tests/run` dispara uma nova execução e devolve JSON com o resultado:

```bash
curl -u "<basic-auth-user>:<basic-auth-pass>" \
  -X POST "https://<your-host>/tests/run" \
  -H "Accept: application/json"
```

Se estiver usando `cmd.exe` no Windows, substitua as barras invertidas por `^` no fim das linhas.

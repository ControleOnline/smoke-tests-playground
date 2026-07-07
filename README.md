[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/controleonline/smoke-tests-playground/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/controleonline/smoke-tests-playground/?branch=master)

# Smoke Tests Playground

Bundle Symfony para expor smoke tests browser como API JSON.

`composer require controleonline/smoke-tests-playground:dev-master`

O pacote nao renderiza UI HTML. A leitura publica acontece por:

- `GET /tests`
- `GET /tests/index.json`
- `GET /tests/api`

Os artifacts publicados pelos smoke tests ficam disponiveis por:

- `GET /tests/artifacts/{suiteId}/{arquivo}`

O frontend separado em `tests-frontend-tool` consome essa API com `X-API-KEY`.

## O que o Playwright publica

Cada tipo e suite continuam gravando em:

- `var/tests/<type>/<suite>/report.json`
- `var/tests/<type>/<suite>/report.xml`
- `var/tests/<type>/<suite>/*.png`
- `var/tests/<type>/<suite>/*/*.png`

O `report.json` ou `report.xml` fica por suite. O bundle varre todos os tipos e suites e monta um `index.json` agregado com:

- status geral
- progresso geral
- resumo de suites e testes
- resumo de tipos
- lista de tipos
- lista de suites
- testes de cada suite
- etapas de cada teste
- prints com URLs autenticadas

## Instalacao

1. Instale o Node.js com `nvm` no servidor, se ainda nao existir:

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
source ~/.bashrc
nvm install --lts
nvm use --lts
```

2. No projeto consumidor, instale o Playwright e os browsers com o mesmo usuario que executa o app:

```bash
npm install -D @playwright/test
node node_modules/@playwright/test/cli.js install
```

3. Instale o pacote com Composer.
4. Registre o bundle em `config/bundles.php`:

```php
ControleOnline\SmokeTestsPlayground\SmokeTestsPlaygroundBundle::class => ['all' => true],
```

5. Rode o bootstrap do pacote:

```bash
php bin/console smoke-tests-playground:install
```

O instalador escreve:

- `.env` com os defaults do smoke
- `config/routes/smoke_tests_playground.yaml`
- `config/services/smoke_tests_playground.yaml`

Se a instalacao dos browsers falhar por permissao, o comando imprime instrucoes para executar como `root`.

## Variaveis de ambiente

- `PLAYWRIGHT_BROWSERS_PATH="0"` evita depender do cache global do usuario.
- `SMOKE_TESTS_PLAYGROUND_TESTS_PATH` aponta para a raiz dos smoke tests, por padrao `var/tests`.
- `SMOKE_TESTS_PLAYGROUND_RUN_COMMAND` define o comando do runner, por padrao:

```bash
node node_modules/@playwright/test/cli.js test --config=playwright.config.cjs tests/browser/*.spec.js
```

- `SMOKE_TESTS_PLAYGROUND_RUN_WORKDIR` define o diretorio de execucao.
- `SMOKE_TESTS_PLAYGROUND_RUN_TIMEOUT` define o timeout em segundos.

## Rotas

- `GET /tests` retorna o mesmo JSON de `GET /tests/index.json`
- `GET /tests/index.json` retorna o indice agregado
- `GET /tests/api` retorna o mesmo JSON para compatibilidade
- `GET /tests/artifacts/{suiteId}/{arquivo}` entrega os artifacts publicados
- `POST /tests/run` continua disponivel para disparar o runner do backend

## Contrato do indice

O indice publico tem a estrutura geral:

```json
{
  "generatedAt": "2026-07-06T18:51:19.924Z",
  "status": "failed",
  "progress": 50,
  "message": "1 suite com falha em 2 publicadas.",
  "lastRunAt": "2026-07-06T18:51:19.924Z",
  "summary": {
    "types": {
      "total": 2,
      "passed": 1,
      "failed": 1
    },
    "suites": {
      "total": 2,
      "passed": 1,
      "failed": 1
    },
    "tests": {
      "total": 2,
      "passed": 1,
      "failed": 1
    }
  },
  "types": [],
  "suites": []
}
```

Cada tipo publica:

- `type`
- `displayName`
- `status`
- `progress`
- `message`
- `summary`
- `suites[]`

Cada suite publica:

- `type`
- `typeDisplayName`
- `suite`
- `suitePath`
- `suiteId`
- `displayName`
- `generatedAt`
- `updatedAt`
- `status`
- `summary`
- `tests[]`
- `links.report`

Cada screenshot publica:

- `label`
- `name`
- `url`
- `mimeType`
- `kind`
- `available`

## Frontend separado

O projeto `tests-frontend-tool` consome a API via `.env`:

```bash
API_ENTRYPOINT=https://staging.frethical.com
HTACCESS_USER=<user>
HTACCESS_PASSWORD=<password>
```

Ele nao executa smoke tests. Ele so le `index.json`, mostra suites/tests/etapas e faz preview dos artifacts.

## Conferencia manual

Exemplo de leitura do indice:

```bash
curl -H "Accept: application/json" \
  -H "X-API-KEY: <api-key>" \
  "https://<your-host>/tests/index.json"
```

Exemplo de artifact:

```bash
curl -H "X-API-KEY: <api-key>" \
  "https://<your-host>/tests/artifacts/<suiteId>/01-login-screen.png" \
  --output login-screen.png
```

## Testes

O pacote tem testes para:

- indice vazio
- multiplas suites
- JSON invalido
- resposta de run
- entrega de artifacts

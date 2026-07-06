# Smoke Tests Playground

Bundle Symfony para expor os smoke tests browser como API JSON.

O pacote não renderiza UI HTML. A leitura pública acontece por:

- `GET /tests`
- `GET /tests/index.json`
- `GET /tests/api`

Os artifacts publicados pelos smoke tests ficam disponíveis por:

- `GET /tests/artifacts/{suite}/{arquivo}`

O frontend separado em `tests-frontend-tool` consome essa API com `X-API-KEY`.

## O que o Playwright publica

Cada suite continua gravando em:

- `var/tests/browser-smoke/<suite>/report.json`
- `var/tests/browser-smoke/<suite>/*.png`
- `var/tests/browser-smoke/<suite>/*/*.png`

O `report.json` fica por suite. O bundle varre todas as suites e monta um `index.json` agregado com:

- status geral
- progresso geral
- resumo de suites e testes
- lista de suites
- testes de cada suite
- etapas de cada teste
- prints com URLs autenticadas

## Instalação

1. Instale o Node.js com `nvm` no servidor, se ainda não existir:

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
source ~/.bashrc
nvm install --lts
nvm use --lts
```

2. No projeto consumidor, instale o Playwright e os browsers com o mesmo usuário que executa o app:

```bash
npm install -D @playwright/test
npm run test:browser:install
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

Se a instalação dos browsers falhar por permissão, o comando imprime instruções para executar como `root`.

## Variáveis de ambiente

- `PLAYWRIGHT_BROWSERS_PATH="0"` evita depender do cache global do usuário.
- `SMOKE_TESTS_PLAYGROUND_TESTS_PATH` aponta para a raiz dos smoke tests, por padrão `var/tests/browser-smoke`.
- `SMOKE_TESTS_PLAYGROUND_RUN_COMMAND` define o comando do runner, por padrão:

```bash
node node_modules/@playwright/test/cli.js test --config=playwright.config.cjs tests/browser/*.spec.js
```

- `SMOKE_TESTS_PLAYGROUND_RUN_WORKDIR` define o diretório de execução.
- `SMOKE_TESTS_PLAYGROUND_RUN_TIMEOUT` define o timeout em segundos.

## Rotas

- `GET /tests` retorna o mesmo JSON de `GET /tests/index.json`
- `GET /tests/index.json` retorna o índice agregado
- `GET /tests/api` retorna o mesmo JSON para compatibilidade
- `GET /tests/artifacts/{suite}/{arquivo}` entrega os artifacts publicados
- `POST /tests/run` continua disponível para disparar o runner do backend

## Contrato do índice

O índice público tem a estrutura geral:

```json
{
  "generatedAt": "2026-07-06T18:51:19.924Z",
  "status": "failed",
  "progress": 50,
  "message": "1 suite com falha em 2 publicadas.",
  "lastRunAt": "2026-07-06T18:51:19.924Z",
  "summary": {
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
  "suites": []
}
```

Cada suite publica:

- `suite`
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
VITE_API_BASE_URL=https://staging.frethical.com
VITE_API_KEY=<api-key>
```

Ele não executa smoke tests. Ele só lê `index.json`, mostra suites/tests/etapas e faz preview dos artifacts.

## Conferência manual

Exemplo de leitura do índice:

```bash
curl -H "Accept: application/json" \
  -H "X-API-KEY: <api-key>" \
  "https://<your-host>/tests/index.json"
```

Exemplo de artifact:

```bash
curl -H "X-API-KEY: <api-key>" \
  "https://<your-host>/tests/artifacts/transporter-login/01-login-screen.png" \
  --output login-screen.png
```

## Testes

O pacote tem testes para:

- índice vazio
- múltiplas suites
- JSON inválido
- resposta de run
- entrega de artifacts


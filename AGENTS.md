## Ponto de entrada

- A documentação funcional e de regras deste modulo vive na wiki do proprio repositório e na wiki principal da API.
- Regras transversais de qualidade, modularizacao e limites de componente vivem em `https://github.com/ControleOnline/agents-mcp/blob/master/skills/shared/code-quality.md`.
- Quando houver detalhe especifico de implementacao, prefira comentar no codigo em ingles perto da regra.
- Este arquivo deve ficar curto e servir apenas como ponte para as fontes oficiais.

## Navegação da wiki

| Home | Categoria | Página | Módulos relacionados |
| --- | --- | --- | --- |
| [wiki Home](https://github.com/ControleOnline/smoke-tests-playground/wiki/Home) | Contratos | [Playground JSON: flowchartIds e links do admin](https://github.com/ControleOnline/smoke-tests-playground/wiki/Playground-JSON-flowchartIds-e-links-admin) | app-community#601, admin flowcharts |
| [wiki Home](https://github.com/ControleOnline/smoke-tests-playground/wiki/Home) | Operação | [Instalacao](https://github.com/ControleOnline/smoke-tests-playground/wiki/Instalacao) | api-community |
| [wiki Home](https://github.com/ControleOnline/smoke-tests-playground/wiki/Home) | Referência | [README na wiki](https://github.com/ControleOnline/smoke-tests-playground/wiki/README) | tests-frontend-tool |

## Código de contrato (flowchart)

- `src/Service/SmokeFlowchartMetadata.php` — normalização de `flowchartIds` / `flowchartLinks` / `flowKey`
- `src/Service/SmokeTestsIndexFactory.php` — índice `GET /tests` com seção `flowcharts`

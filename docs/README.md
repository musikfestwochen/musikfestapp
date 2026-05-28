# Docs Local Development

This project uses MkDocs and Material theme.

## Prerequisites

- `uv` installed
- Python `3.12+`
- Node.js `22+` (`npx` required for Mermaid SVG rendering)

## Build and Validate

From repository root:

```bash
uv sync --frozen
uv run mkdocs build --strict
```

`--strict` fails build on documentation warnings.

## Run Local Preview

```bash
uv run mkdocs serve
```

Open `http://127.0.0.1:8001`.

## Cleanup

```bash
rm -rf .venv
```

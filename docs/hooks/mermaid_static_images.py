from __future__ import annotations

import hashlib
import re
import subprocess
import tempfile
from pathlib import Path

MERMAID_FENCE_PATTERN = re.compile(
    r'^```mermaid[^\S\r\n]*\r?\n(.*?)\r?\n```[ \t]*$',
    re.DOTALL | re.MULTILINE,
)
RENDERED_HASHES: set[str] = set()


def _render_mermaid_to_svg(source: str, destination: Path) -> None:
    destination.parent.mkdir(parents=True, exist_ok=True)

    with tempfile.NamedTemporaryFile('w', suffix='.mmd', delete=False, encoding='utf-8') as handle:
        handle.write(source)
        temp_source_path = Path(handle.name)

    try:
        command = [
            'npx',
            '--yes',
            '@mermaid-js/mermaid-cli@11.6.0',
            '-i',
            str(temp_source_path),
            '-o',
            str(destination),
            '-b',
            'transparent',
        ]
        subprocess.run(command, check=True, capture_output=True, text=True)
    except subprocess.CalledProcessError as error:
        raise RuntimeError(error.stderr.strip() or 'Failed to render Mermaid diagram.') from error
    finally:
        if temp_source_path.exists():
            temp_source_path.unlink()


def _image_path_for_page(page_url: str, image_name: str) -> str:
    normalized = page_url.strip('/')
    depth = 0 if not normalized else normalized.count('/') + 1
    prefix = '../' * depth
    return f'{prefix}assets/diagrams/{image_name}'


def _normalize_svg_dimensions(svg_path: Path, source_hash: str) -> None:
    original_content = svg_path.read_text(encoding='utf-8')
    content = original_content

    viewbox_match = re.search(r'\bviewBox="([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)"', content)
    if not viewbox_match:
        return

    width = int(float(viewbox_match.group(3)))
    height = int(float(viewbox_match.group(4)))

    svg_id = f'mermaid-{source_hash}'

    if re.search(r'<svg\b[^>]*\bid="[^"]+"', content):
        content = re.sub(r'<svg\b([^>]*?)\bid="[^"]+"', rf'<svg\1id="{svg_id}"', content, count=1)
    else:
        content = re.sub(r'<svg\b', f'<svg id="{svg_id}"', content, count=1)

    content = content.replace('#my-svg', f'#{svg_id}')

    if re.search(r'\bwidth="[^"]+"', content):
        content = re.sub(r'\bwidth="[^"]+"', f'width="{width}"', content, count=1)
    else:
        content = re.sub(r'<svg\b', f'<svg width="{width}"', content, count=1)

    if re.search(r'\bheight="[^"]+"', content):
        content = re.sub(r'\bheight="[^"]+"', f'height="{height}"', content, count=1)
    else:
        content = re.sub(r'<svg\b', f'<svg height="{height}"', content, count=1)

    if content != original_content:
        svg_path.write_text(content, encoding='utf-8')


def on_page_markdown(markdown: str, page, config, files):
    def replace_fence(match: re.Match[str]) -> str:
        source = match.group(1).strip()
        source_hash = hashlib.sha256(source.encode('utf-8')).hexdigest()[:16]
        svg_name = f'mermaid-{source_hash}.svg'
        svg_path = Path(config['docs_dir']) / 'assets' / 'diagrams' / svg_name

        if source_hash not in RENDERED_HASHES and not svg_path.exists():
            _render_mermaid_to_svg(source, svg_path)

        _normalize_svg_dimensions(svg_path, source_hash)

        RENDERED_HASHES.add(source_hash)

        image_path = _image_path_for_page(page.url, svg_name)
        return f'<img src="{image_path}" alt="Mermaid diagram" loading="lazy" class="mermaid-diagram">'

    return MERMAID_FENCE_PATTERN.sub(replace_fence, markdown)

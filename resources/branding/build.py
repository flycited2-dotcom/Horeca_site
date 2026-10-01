"""Собирает значок и превью сайта из исходников в этой папке (ТЗ §9, §14).

Запуск из корня проекта (нужны Python 3 с Pillow и Google Chrome; шрифт Manrope — из node_modules):

    python resources/branding/build.py

Результат — public/favicon.svg, favicon.ico (16, 32 и 48 px), apple-touch-icon.png (180 px)
и og-image.png (1200×630). Готовые файлы лежат в git: на сервере Chrome не нужен.
"""

import io
import os
import subprocess
import tempfile
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[2]
SRC = Path(__file__).resolve().parent
PUBLIC = ROOT / 'public'
CHROME = os.environ.get('CHROME_PATH', r'C:\Program Files\Google\Chrome\Application\chrome.exe')


def shot(url: str, out: Path, width: int, height: int, transparent: bool = False) -> None:
    args = [CHROME, '--headless=new', '--disable-gpu', '--hide-scrollbars', '--force-device-scale-factor=1',
            f'--window-size={width},{height}', f'--screenshot={out}']
    if transparent:
        args.append('--default-background-color=00000000')
    subprocess.run(args + [url], check=True, capture_output=True, timeout=90)


def main() -> None:
    PUBLIC.mkdir(exist_ok=True)

    (PUBLIC / 'favicon.svg').write_text((SRC / 'icon.svg').read_text(encoding='utf-8'), encoding='utf-8', newline='\n')

    with tempfile.TemporaryDirectory() as tmp:
        tmp_dir = Path(tmp)

        big = tmp_dir / 'icon-512.png'
        shot((SRC / 'icon.svg').as_uri(), big, 512, 512, transparent=True)
        icon = Image.open(big).convert('RGBA')
        sizes = [icon.resize((s, s), Image.LANCZOS) for s in (16, 32, 48)]
        icon.resize((48, 48), Image.LANCZOS).save(
            PUBLIC / 'favicon.ico', format='ICO', sizes=[(16, 16), (32, 32), (48, 48)], append_images=sizes[:2]
        )

        apple = tmp_dir / 'apple-512.png'
        shot((SRC / 'apple-icon.svg').as_uri(), apple, 512, 512)
        Image.open(apple).convert('RGB').resize((180, 180), Image.LANCZOS).save(PUBLIC / 'apple-touch-icon.png', optimize=True)

    og = tempfile.NamedTemporaryFile(suffix='.png', delete=False)
    og.close()
    try:
        shot((SRC / 'og.html').as_uri(), Path(og.name), 1200, 630)
        Image.open(og.name).convert('RGB').save(PUBLIC / 'og-image.png', optimize=True)
    finally:
        os.unlink(og.name)

    for name in ('favicon.svg', 'favicon.ico', 'apple-touch-icon.png', 'og-image.png'):
        print(f'{name}: {(PUBLIC / name).stat().st_size} байт')


if __name__ == '__main__':
    main()

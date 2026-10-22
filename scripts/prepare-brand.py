"""Prepare web-sized brand assets from the supplied originals in tasks/."""
from pathlib import Path
from PIL import Image, ImageOps

root = Path(__file__).resolve().parents[1]
source = root / 'tasks'
target = root / 'public/assets/brand'
target.mkdir(parents=True, exist_ok=True)
for filename, output in [('Белая вариация логотипа.jpg', 'logo-light.webp'), ('чёрный логотип.jpg', 'logo-dark.webp')]:
    with Image.open(source / filename) as image:
        image.thumbnail((320, 320), Image.Resampling.LANCZOS)
        image.convert('RGB').save(target / output, quality=90)
with Image.open(source / 'kod_novorossii_512x515.png') as original:
    image = ImageOps.fit(original.convert('RGB'), (512, 512), method=Image.Resampling.LANCZOS)
    for size in (192, 512):
        image.resize((size, size), Image.Resampling.LANCZOS).save(root / f'public/icon-{size}.png')
        canvas = Image.new('RGB', (size, size), image.getpixel((0, 0)))
        content = image.resize((round(size * .7), round(size * .7)), Image.Resampling.LANCZOS)
        canvas.paste(content, ((size-content.width)//2, (size-content.height)//2))
        canvas.save(root / f'public/icon-maskable-{size}.png')
    image.resize((180, 180), Image.Resampling.LANCZOS).save(root / 'public/apple-touch-icon.png')
    image.save(root / 'public/favicon.ico', sizes=[(32, 32), (48, 48), (64, 64)])

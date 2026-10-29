import io, sys, cairosvg
from PIL import Image, ImageDraw, ImageFont, ImageFilter
svg, out = sys.argv[1], sys.argv[2]
LOGO_W = 760
logo = Image.open(io.BytesIO(cairosvg.svg2png(url=svg, output_width=LOGO_W))).convert("RGBA")
lw, lh = logo.size
font = ImageFont.truetype("/usr/share/fonts/opentype/inter/Inter-Medium.otf", 58)
text = "www.beachrex.com"
tb = font.getbbox(text)
tw, th = tb[2]-tb[0], tb[3]-tb[1]
pad, gap = 24, 22
W = max(lw, tw) + 2*pad
H = pad + lh + gap + th + pad
fg = Image.new("RGBA", (W, H), (0,0,0,0))
fg.alpha_composite(logo, ((W-lw)//2, pad))
d = ImageDraw.Draw(fg)
# letter-spaced text, centred under the logo
spacing = 6
widths = [font.getlength(c) for c in text]
total = sum(widths) + spacing*(len(text)-1)
x = (W-total)/2; y = pad + lh + gap - tb[1]
for c, w in zip(text, widths):
    d.text((x, y), c, font=font, fill=(255,255,255,255)); x += w + spacing
# soft drop shadow from the alpha channel
alpha = fg.split()[3]
shadow_a = alpha.point(lambda v: int(v*0.55)).filter(ImageFilter.GaussianBlur(7))
shadow = Image.new("RGBA", (W, H), (0,0,0,0)); shadow.putalpha(shadow_a)
off = Image.new("RGBA", (W, H), (0,0,0,0)); off.alpha_composite(shadow, (3, 4))
res = Image.new("RGBA", (W, H), (0,0,0,0)); res.alpha_composite(off); res.alpha_composite(fg)
res.save(out, optimize=True)
print(res.size)

import sys

def fix_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Try fully automated cp1252 reverse
    try:
        new_content = content.encode('cp1252').decode('utf-8')
        print(f"Success exact reverse for {filepath}")
    except Exception as e:
        print(f"Error reversing {filepath}: {e}, using manual replacements")
        replacements = {
            "ÃƒÂ©": "é",
            "ÃƒÂ ": "à ",
            "ÃƒÂ¨": "è",
            "ÃƒÂª": "ê",
            "ÃƒÂ®": "î",
            "ÃƒÂ´": "ô",
            "ÃƒÂ»": "û",
            "Ã¢Â Â¤Ã¯Â¸Â": "❤️",
            "Ã¢Â Â¤Ã¯Â¸Â ": "❤️ ",
            "Ã¢â‚¬â€ ": "—",
            "Ãƒâ€°": "É",
            "Ãƒâ‚¬": "À",
            "Ã°Å¸Å’Â¾": "🌾",
            "Ã°Å¸â€œÅ¡": "📚",
            "Ã°Å¸Å½Â¤": "🎤",
            "Ã°Å¸â€ Â§": "🔧",
            "Ã¢Â Â¤": "❤️",
            "ÃƒÂ§": "ç",
            "Ã¢â‚¬â„¢": "'",
        }
        for k, v in replacements.items():
            content = content.replace(k, v)
        new_content = content

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(new_content)

for filepath in sys.argv[1:]:
    fix_file(filepath)

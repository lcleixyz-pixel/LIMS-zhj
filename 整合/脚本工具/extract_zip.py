import zipfile, os, sys

zip_path = sys.argv[1]
out_dir = sys.argv[2]

os.makedirs(out_dir, exist_ok=True)

with zipfile.ZipFile(zip_path, 'r') as zf:
    for info in zf.infolist():
        # Try to recover UTF-8 filenames from cp437-mangled encoding
        try:
            name = info.filename.encode('cp437').decode('utf-8')
        except (UnicodeDecodeError, UnicodeEncodeError):
            name = info.filename
        # Normalize separators
        name = name.replace('\\', '/')
        target = os.path.join(out_dir, name)
        if info.is_dir():
            os.makedirs(target, exist_ok=True)
        else:
            os.makedirs(os.path.dirname(target), exist_ok=True)
            with zf.open(info) as src, open(target, 'wb') as dst:
                dst.write(src.read())
        print(name)

print(f"\nExtracted {sum(1 for _ in open(os.devnull))} files to {out_dir}")
print("Done!")

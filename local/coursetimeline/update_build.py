import os
import shutil

src_path = 'amd/src/finder.js'
dest_path = 'amd/build/finder.min.js'

# Ensure build directory exists
os.makedirs(os.path.dirname(dest_path), exist_ok=True)

shutil.copyfile(src_path, dest_path)

print(f"Copied {src_path} to {dest_path}")

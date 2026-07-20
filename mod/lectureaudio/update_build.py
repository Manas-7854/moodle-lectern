"""Regenerate amd/build/*.min.js from amd/src/*.js.

This is a stand-in for Moodle's own `grunt amd` task. If this plugin sits
inside a full Moodle codebase, prefer running `grunt amd` (or `grunt` for
everything) from the Moodle root instead: that uses Moodle core's own
Babel/Terser configuration and is the canonical way to build AMD modules.

This script exists for standalone plugin development (no Moodle core
checkout available) and requires Node.js + npx on PATH; it shells out to
`npx terser` to produce a real minified file, rather than a plain
source-to-build copy.
"""
import os
import shutil
import subprocess

MODULES = [
    ('mod_lectureaudio/recorder', 'amd/src/recorder.js', 'amd/build/recorder.min.js'),
]

BANNER = "/*\n Copyright (c) Moodle Plugins Portfolio. Provided under GPL v3 or any later version.\n Source: {src}\n*/\n"


def build(modulename, src_path, dest_path):
    os.makedirs(os.path.dirname(dest_path), exist_ok=True)

    with open(src_path, encoding='utf-8') as f:
        source = f.read()

    # Inject the AMD module name, as Moodle's real build does.
    named_src = source.replace('define([', f'define("{modulename}", [', 1)

    tmp_named = dest_path + '.named.tmp.js'
    with open(tmp_named, 'w', encoding='utf-8') as f:
        f.write(named_src)

    try:
        result = subprocess.run(
            ['npx', '--yes', 'terser', tmp_named, '--compress', '--mangle'],
            capture_output=True, text=True, timeout=120, check=True,
        )
        with open(dest_path, 'w', encoding='utf-8') as f:
            f.write(BANNER.format(src=src_path))
            f.write(result.stdout)
        print(f'Built {dest_path} (minified via terser)')
    except (subprocess.CalledProcessError, FileNotFoundError, subprocess.TimeoutExpired) as exc:
        print(f'WARNING: terser unavailable ({exc}); falling back to an unminified copy.')
        print('Run `grunt amd` from a full Moodle codebase for a real production build.')
        shutil.copyfile(src_path, dest_path)
    finally:
        if os.path.exists(tmp_named):
            os.remove(tmp_named)


if __name__ == '__main__':
    for modulename, src, dest in MODULES:
        build(modulename, src, dest)

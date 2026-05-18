#!/usr/bin/env python3
"""Copy i18n strings from git HEAD includes/class-ergo-admin.php into core when mojibake."""
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CORE = ROOT / "core" / "class-ergo-admin.php"

MOJIBAKE_RE = re.compile(
    r"Р\s*В|Р\xa0В|╨|вЂ|РЋ|Р'В|РЎ|РІР‚"
)


def git_admin_text() -> str:
    raw = subprocess.check_output(
        ["git", "show", "HEAD:includes/class-ergo-admin.php"],
        cwd=str(ROOT),
    )
    return raw.decode("utf-8")


def is_mojibake_line(line: str) -> bool:
    if "worldstat-ergonomics" not in line:
        return False
    if not re.search(r"__\(|esc_html_e\(|esc_attr_e\(|_e\(|_n\(", line):
        return False
    return bool(MOJIBAKE_RE.search(line))


def extract_string_literals(line: str) -> list[str]:
    return re.findall(r"'((?:[^'\\]|\\.)*)'", line)


def replace_strings_in_line(line: str, new_strings: list[str]) -> str:
    idx = 0

    def repl(m: re.Match[str]) -> str:
        nonlocal idx
        if idx < len(new_strings):
            s = new_strings[idx].replace("\\", "\\\\").replace("'", "\\'")
            idx += 1
            return "'" + s + "'"
        return m.group(0)

    return re.sub(r"'((?:[^'\\]|\\.)*)'", repl, line)


def split_functions(text: str) -> dict[str, list[str]]:
    lines = text.splitlines()
    funcs: dict[str, list[str]] = {"__global__": []}
    current = "__global__"
    for line in lines:
        m = re.match(r"\s*(?:public|private|protected)\s+function\s+(\w+)\s*\(", line)
        if m:
            current = m.group(1)
            funcs[current] = []
        funcs.setdefault(current, []).append(line)
    return funcs


def main() -> None:
    git_lines = git_admin_text().splitlines()
    core_lines = CORE.read_text(encoding="utf-8").splitlines()
    git_funcs = split_functions("\n".join(git_lines))

    out: list[str] = []
    current = "__global__"
    fixed = 0

    for line in core_lines:
        m = re.match(r"\s*(?:public|private|protected)\s+function\s+(\w+)\s*\(", line)
        if m:
            current = m.group(1)

        if is_mojibake_line(line) and current in git_funcs:
            skeleton = re.sub(r"'(?:[^'\\]|\\.)*'", "''", line).strip()
            for gl in git_funcs[current]:
                if re.sub(r"'(?:[^'\\]|\\.)*'", "''", gl).strip() == skeleton:
                    new_strs = extract_string_literals(gl)
                    new_line = replace_strings_in_line(line, new_strs)
                    if new_line != line:
                        line = new_line
                        fixed += 1
                    break

        out.append(line)

    CORE.write_text("\n".join(out) + "\n", encoding="utf-8")
    print(f"Fixed {fixed} lines")


if __name__ == "__main__":
    main()

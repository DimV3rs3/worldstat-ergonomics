#!/usr/bin/env python3
"""Fix double UTF-8 mojibake in core/class-ergo-admin.php __() strings."""
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CORE = ROOT / "core" / "class-ergo-admin.php"


def try_unmojibake(s: str) -> str | None:
    for _ in range(4):
        try:
            n = s.encode("latin-1").decode("utf-8")
        except (UnicodeDecodeError, UnicodeEncodeError):
            return None
        if n == s:
            break
        s = n
    if re.search(r"[\u0400-\u04FF]", s) and "Р В" not in s and "Р В" not in s:
        return s
    return None


def main() -> None:
    text = CORE.read_text(encoding="utf-8")

    def repl(match: re.Match[str]) -> str:
        inner = match.group(1)
        if "Р В" not in inner and "Р В" not in inner:
            return match.group(0)
        fixed = try_unmojibake(inner)
        if fixed:
            escaped = fixed.replace("\\", "\\\\").replace("'", "\\'")
            return f"__( '{escaped}', 'worldstat-ergonomics' )"
        return match.group(0)

    # __() with single-quoted strings
    new_text = re.sub(
        r"__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'worldstat-ergonomics'\s*\)",
        repl,
        text,
    )

    # esc_html_e / esc_attr_e same domain
    def repl_e(match: re.Match[str]) -> str:
        fn, inner = match.group(1), match.group(2)
        if "Р В" not in inner and "Р В" not in inner:
            return match.group(0)
        fixed = try_unmojibake(inner)
        if fixed:
            escaped = fixed.replace("\\", "\\\\").replace("'", "\\'")
            return f"{fn}( '{escaped}', 'worldstat-ergonomics' )"
        return match.group(0)

    new_text = re.sub(
        r"(esc_html_e|esc_attr_e)\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'worldstat-ergonomics'\s*\)",
        repl_e,
        new_text,
    )

    # block comments with mojibake on doc lines
    lines = new_text.splitlines()
    out_lines = []
    for line in lines:
        if ("Р В" in line or "Р В" in line) and line.strip().startswith("*"):
            stripped = line.strip().lstrip("*").strip()
            fixed = try_unmojibake(stripped)
            if fixed:
                indent = line[: line.index("*")]
                line = indent + "* " + fixed
        out_lines.append(line)
    new_text = "\n".join(out_lines) + ("\n" if text.endswith("\n") else "")

    if new_text != text:
        CORE.write_text(new_text, encoding="utf-8", newline="\n")
        print("Fixed", CORE)
    else:
        print("No changes")


if __name__ == "__main__":
    main()

"""Copy only regular, allowlisted skill-update files from an untrusted artifact."""

import json
from pathlib import Path
import shutil
import stat
import sys


SKILL = Path(".agents/skills/security-audit")
LOCK = Path("skills-lock.json")


def promote(source: Path, destination: Path) -> None:
    files = []
    total_size = 0

    def inspect(path: Path) -> None:
        nonlocal total_size
        relative = path.relative_to(source)
        info = path.lstat()
        if relative != Path("."):
            allowed = relative in (LOCK, SKILL) or SKILL in relative.parents or relative in SKILL.parents
            if not allowed or ".git" in relative.parts:
                raise ValueError(f"Unexpected artifact path: {relative}")
        if stat.S_ISDIR(info.st_mode):
            for child in path.iterdir():
                inspect(child)
        elif stat.S_ISREG(info.st_mode) and info.st_nlink == 1:
            if relative != LOCK and SKILL not in relative.parents:
                raise ValueError(f"Unexpected artifact file: {relative}")
            total_size += info.st_size
            if total_size > 5 * 1024 * 1024:
                raise ValueError("Skill update exceeds 5 MiB")
            files.append(relative)
        else:
            raise ValueError(f"Artifact entry is not a regular file/directory: {relative}")

    inspect(source)
    if LOCK not in files or SKILL / "SKILL.md" not in files:
        raise ValueError("Artifact must contain skill entry point and lockfile")
    locked = json.loads((source / LOCK).read_text())
    entry = locked["skills"]["security-audit"]
    if set(locked["skills"]) != {"security-audit"} or (
        entry["source"], entry["sourceType"], entry["skillPath"]
    ) != ("cloudflare/security-audit-skill", "github", "skills/security-audit/SKILL.md"):
        raise ValueError("Unexpected skill source in lockfile")
    for relative in (*reversed(SKILL.parents), SKILL, LOCK):
        if (destination / relative).is_symlink():
            raise ValueError(f"Destination must not be a symlink: {relative}")
    if (destination / SKILL).exists():
        shutil.rmtree(destination / SKILL)
    for relative in files:
        target = destination / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(source / relative, target)


if __name__ == "__main__":
    promote(Path(sys.argv[1]), Path(sys.argv[2]))

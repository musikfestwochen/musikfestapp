"""Copy only regular, allowlisted skill-update files from an untrusted artifact."""

import json
from pathlib import Path
import re
import shutil
import stat
import subprocess
import sys


SKILL = Path(".agents/skills/security-audit")
LOCK = Path("skills-lock.json")
SKILL_HASH_SCRIPT = r"""
import { readdir, readFile } from "node:fs/promises";
import { join, relative } from "node:path";
import { createHash } from "node:crypto";

const baseDir = process.argv[1];
const files = [];

async function collectFiles(currentDir) {
  const entries = await readdir(currentDir, { withFileTypes: true });
  await Promise.all(entries.map(async (entry) => {
    const fullPath = join(currentDir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name === ".git" || entry.name === "node_modules") return;
      await collectFiles(fullPath);
    } else if (entry.isFile()) {
      files.push({
        relativePath: relative(baseDir, fullPath).split("\\").join("/"),
        content: await readFile(fullPath),
      });
    }
  }));
}

await collectFiles(baseDir);
files.sort((left, right) => left.relativePath.localeCompare(right.relativePath));
const hash = createHash("sha256");
for (const file of files) {
  hash.update(file.relativePath);
  hash.update(file.content);
}
console.log(hash.digest("hex"));
"""


def skill_content_hash(skill_directory: Path) -> str:
    result = subprocess.run(
        ["node", "--input-type=module", "-e", SKILL_HASH_SCRIPT, str(skill_directory)],
        capture_output=True,
        check=True,
        text=True,
        timeout=60,
    )

    return result.stdout.strip()


def promote(source: Path, destination: Path) -> None:
    files = []
    total_size = 0

    def inspect(path: Path) -> None:
        nonlocal total_size
        relative = path.relative_to(source)
        info = path.lstat()
        if relative != Path("."):
            if "node_modules" in relative.parts:
                raise ValueError(f"Unexpected generated dependency path: {relative}")
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
    expected_hash = entry.get("computedHash")
    if not isinstance(expected_hash, str) or re.fullmatch(r"[0-9a-f]{64}", expected_hash) is None:
        raise ValueError("Lockfile must contain a valid skill content hash")
    actual_hash = skill_content_hash(source / SKILL)
    if expected_hash != actual_hash:
        raise ValueError("Skill content does not match lockfile hash")
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

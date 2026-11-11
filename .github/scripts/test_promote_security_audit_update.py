import json
import os
from pathlib import Path
import tempfile
import unittest

from promote_security_audit_update import EXECUTABLES, LOCK, SKILL, promote, write_executables


class PromoteSkillUpdateTest(unittest.TestCase):
    def test_copies_update_and_removes_stale_files(self):
        with tempfile.TemporaryDirectory() as root:
            source, destination = self.fixture(Path(root))
            (destination / SKILL).mkdir(parents=True)
            (destination / SKILL / "obsolete.md").write_text("old")
            promote(source, destination)
            self.assertEqual((destination / SKILL / "SKILL.md").read_text(), "skill")
            self.assertFalse((destination / SKILL / "obsolete.md").exists())
            self.assertTrue((destination / LOCK).is_file())

    def test_rejects_unexpected_paths_links_and_hashes_before_writing(self):
        for kind in (
            "outside",
            "symlink",
            "hardlink",
            "git",
            "node_modules",
            "wrong-source",
            "missing-hash",
            "wrong-hash",
            "content-changed",
            "missing-manifest",
            "invalid-manifest",
            "duplicate-manifest",
            "non-list-manifest",
            "non-string-manifest",
        ):
            with self.subTest(kind=kind), tempfile.TemporaryDirectory() as root:
                source, destination = self.fixture(Path(root))
                if kind == "outside":
                    (source / "unexpected.txt").write_text("bad")
                elif kind == "symlink":
                    (source / SKILL / "linked").symlink_to(source / LOCK)
                elif kind == "hardlink":
                    os.link(source / LOCK, source / SKILL / "linked")
                elif kind == "git":
                    (source / SKILL / ".git").mkdir()
                elif kind == "node_modules":
                    (source / SKILL / "node_modules").mkdir()
                elif kind == "content-changed":
                    (source / SKILL / "SKILL.md").write_text("modified skill")
                elif kind == "missing-manifest":
                    (source / EXECUTABLES).unlink()
                elif kind == "invalid-manifest":
                    (source / EXECUTABLES).write_text('["../outside"]')
                elif kind == "duplicate-manifest":
                    (source / EXECUTABLES).write_text(json.dumps([str(SKILL / "SKILL.md")] * 2))
                elif kind == "non-list-manifest":
                    (source / EXECUTABLES).write_text('{}')
                elif kind == "non-string-manifest":
                    (source / EXECUTABLES).write_text('[null]')
                else:
                    lock = json.loads((source / LOCK).read_text())
                    entry = lock["skills"]["security-audit"]
                    if kind == "wrong-source":
                        entry["source"] = "other/repo"
                    elif kind == "missing-hash":
                        del entry["computedHash"]
                    elif kind == "wrong-hash":
                        entry["computedHash"] = "0" * 64
                    (source / LOCK).write_text(json.dumps(lock))
                with self.assertRaises(ValueError):
                    promote(source, destination)
                self.assertEqual(list(destination.iterdir()), [])

    def test_applies_upstream_modes_after_artifact_permission_loss(self):
        with tempfile.TemporaryDirectory() as root:
            source, destination = self.fixture(Path(root))
            (destination / SKILL).mkdir(parents=True)
            (destination / SKILL / "SKILL.md").write_text("old")
            (source / SKILL / "SKILL.md").chmod(0o755)
            write_executables(source)
            (source / SKILL / "SKILL.md").chmod(0o644)
            promote(source, destination)
            self.assertEqual((destination / SKILL / "SKILL.md").read_text(), "skill")
            self.assertEqual((destination / SKILL / "SKILL.md").stat().st_mode & 0o777, 0o755)
            self.assertFalse((destination / EXECUTABLES).exists())
            write_executables(source)
            promote(source, destination)
            self.assertEqual((destination / SKILL / "SKILL.md").stat().st_mode & 0o777, 0o644)

    def test_applies_executable_mode_to_new_files(self):
        with tempfile.TemporaryDirectory() as root:
            source, destination = self.fixture(Path(root))
            (source / SKILL / "SKILL.md").chmod(0o755)
            write_executables(source)
            (source / SKILL / "SKILL.md").chmod(0o644)
            promote(source, destination)
            self.assertEqual((destination / SKILL / "SKILL.md").stat().st_mode & 0o777, 0o755)

    @staticmethod
    def fixture(root):
        source, destination = root / "source", root / "destination"
        (source / SKILL).mkdir(parents=True)
        destination.mkdir()
        (source / SKILL / "SKILL.md").write_text("skill")
        (source / EXECUTABLES).write_text("[]")
        (source / LOCK).write_text(json.dumps({"skills": {"security-audit": {
            "source": "cloudflare/security-audit-skill", "sourceType": "github",
            "skillPath": "skills/security-audit/SKILL.md",
            "computedHash": "23ef6bd59617fdb2b0abbc0db90b0f8a96fc8cdf8bae05edf711c6383c99aeab",
        }}}))
        return source, destination


if __name__ == "__main__":
    unittest.main()

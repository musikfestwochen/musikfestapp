import json
import os
from pathlib import Path
import tempfile
import unittest

from promote_security_audit_update import LOCK, SKILL, promote


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

    @staticmethod
    def fixture(root):
        source, destination = root / "source", root / "destination"
        (source / SKILL).mkdir(parents=True)
        destination.mkdir()
        (source / SKILL / "SKILL.md").write_text("skill")
        (source / LOCK).write_text(json.dumps({"skills": {"security-audit": {
            "source": "cloudflare/security-audit-skill", "sourceType": "github",
            "skillPath": "skills/security-audit/SKILL.md",
            "computedHash": "23ef6bd59617fdb2b0abbc0db90b0f8a96fc8cdf8bae05edf711c6383c99aeab",
        }}}))
        return source, destination


if __name__ == "__main__":
    unittest.main()

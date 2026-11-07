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

    def test_rejects_unexpected_paths_and_links_before_writing(self):
        for kind in ("outside", "symlink", "hardlink", "git", "wrong-source"):
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
                else:
                    lock = json.loads((source / LOCK).read_text())
                    lock["skills"]["security-audit"]["source"] = "other/repo"
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
        }}}))
        return source, destination


if __name__ == "__main__":
    unittest.main()

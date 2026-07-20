#!/usr/bin/env python3
"""自治治理台账机器门禁回归测试。"""

from __future__ import annotations

import copy
import importlib.util
import json
import tempfile
import unittest
from pathlib import Path


HERE = Path(__file__).resolve().parent
PACKAGE_DIR = HERE.parent
VALIDATOR_PATH = HERE / "validate_governance_package.py"
LEDGER_PATH = PACKAGE_DIR / "自治治理主台账-v0.1.json"


def load_validator():
    spec = importlib.util.spec_from_file_location("governance_validator", VALIDATOR_PATH)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"无法加载检查器：{VALIDATOR_PATH}")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class GovernancePackageValidationTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.validator = load_validator()
        cls.ledger = json.loads(LEDGER_PATH.read_text(encoding="utf-8"))

    def test_real_package_passes(self) -> None:
        findings = self.validator.validate_package(PACKAGE_DIR)
        self.assertEqual([], findings)

    def test_invalid_issue_state_is_rejected(self) -> None:
        ledger = copy.deepcopy(self.ledger)
        ledger["findings"][0]["issue_state"] = "done"
        findings = self.validator.validate_ledger(ledger, PACKAGE_DIR)
        self.assertTrue(any(item["code"] == "invalid_issue_state" for item in findings))

    def test_invalid_file_state_is_rejected(self) -> None:
        ledger = copy.deepcopy(self.ledger)
        ledger["file_candidates"][0]["file_state"] = "published"
        findings = self.validator.validate_ledger(ledger, PACKAGE_DIR)
        self.assertTrue(any(item["code"] == "invalid_file_state" for item in findings))

    def test_missing_traceback_is_rejected(self) -> None:
        ledger = copy.deepcopy(self.ledger)
        ledger["findings"][0]["objective_basis"] = []
        findings = self.validator.validate_ledger(ledger, PACKAGE_DIR)
        self.assertTrue(any(item["code"] == "missing_objective_basis" for item in findings))

    def test_bad_payload_hash_is_rejected(self) -> None:
        ledger = copy.deepcopy(self.ledger)
        ledger["file_candidates"][0]["payload_sha256"] = "0" * 64
        findings = self.validator.validate_ledger(ledger, PACKAGE_DIR)
        self.assertTrue(any(item["code"] == "payload_hash_mismatch" for item in findings))

    def test_formal_release_language_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as temp_dir:
            sandbox = Path(temp_dir)
            (sandbox / "候选覆盖层").mkdir()
            payload = sandbox / "候选覆盖层" / "candidate.md"
            payload.write_text("# 候选\n本文件已经正式批准并生效。\n", encoding="utf-8")
            ledger = copy.deepcopy(self.ledger)
            ledger["file_candidates"] = [copy.deepcopy(ledger["file_candidates"][0])]
            ledger["file_candidates"][0]["payload_path"] = "候选覆盖层/candidate.md"
            import hashlib

            ledger["file_candidates"][0]["payload_sha256"] = hashlib.sha256(
                payload.read_bytes()
            ).hexdigest()
            findings = self.validator.validate_ledger(ledger, sandbox)
            self.assertTrue(any(item["code"] == "forbidden_release_claim" for item in findings))


if __name__ == "__main__":
    unittest.main(verbosity=2)

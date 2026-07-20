#!/usr/bin/env python3
"""自治治理台账 v0.3 状态推进门禁测试。"""

from __future__ import annotations

import copy
import importlib.util
import json
import unittest
from pathlib import Path


HERE = Path(__file__).resolve().parent
PACKAGE = HERE.parent
V02 = json.loads((PACKAGE / "自治治理主台账-v0.2.json").read_text(encoding="utf-8"))
V03 = json.loads((PACKAGE / "自治治理主台账-v0.3.json").read_text(encoding="utf-8"))


def load_validator():
    path = HERE / "validate_governance_package_v03.py"
    spec = importlib.util.spec_from_file_location("governance_v03_validator", path)
    if spec is None or spec.loader is None:
        raise RuntimeError("无法加载v0.3检查器")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class GovernanceV03Test(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.validator = load_validator()

    def test_real_v03_passes(self) -> None:
        self.assertEqual([], self.validator.validate_package())

    def test_sim_applied_is_rejected(self) -> None:
        ledger = copy.deepcopy(V03)
        ledger["file_candidates"][2]["file_state"] = "sim_applied"
        findings = self.validator.validate_ledger(ledger, V02)
        self.assertTrue(any(item["code"] == "candidate_state_mismatch" for item in findings))

    def test_finding_cannot_close_before_replay(self) -> None:
        ledger = copy.deepcopy(V03)
        ledger["findings"][6]["issue_state"] = "closed"
        findings = self.validator.validate_ledger(ledger, V02)
        self.assertTrue(any(item["code"] == "finding_state_mismatch" for item in findings))

    def test_candidate_hash_change_is_rejected(self) -> None:
        ledger = copy.deepcopy(V03)
        ledger["file_candidates"][2]["payload_sha256"] = "0" * 64
        findings = self.validator.validate_ledger(ledger, V02)
        self.assertTrue(any(item["code"] == "candidate_payload_changed" for item in findings))

    def test_file001_change_is_rejected(self) -> None:
        ledger = copy.deepcopy(V03)
        ledger["file_candidates"][0]["target_title"] = "被擅自改动"
        findings = self.validator.validate_ledger(ledger, V02)
        self.assertTrue(any(item["code"] == "unapproved_file_change" for item in findings))


if __name__ == "__main__":
    unittest.main(verbosity=2)

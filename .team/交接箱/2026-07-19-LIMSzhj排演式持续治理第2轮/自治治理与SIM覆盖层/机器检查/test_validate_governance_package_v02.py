#!/usr/bin/env python3
"""自治治理台账 v0.2 状态门禁回归测试。"""

from __future__ import annotations

import copy
import importlib.util
import json
import unittest
from pathlib import Path


HERE = Path(__file__).resolve().parent
PACKAGE = HERE.parent
LEDGER = json.loads((PACKAGE / "自治治理主台账-v0.2.json").read_text(encoding="utf-8"))


def load_validator():
    path = HERE / "validate_governance_package_v02.py"
    spec = importlib.util.spec_from_file_location("governance_v02_validator", path)
    if spec is None or spec.loader is None:
        raise RuntimeError("无法加载v0.2台账检查器")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class GovernanceV02Test(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.validator = load_validator()

    def test_real_package_passes(self) -> None:
        self.assertEqual([], self.validator.validate_package(PACKAGE))

    def test_file003_cannot_advance_without_independent_pass(self) -> None:
        ledger = copy.deepcopy(LEDGER)
        ledger["file_candidates"][2]["file_state"] = "verified_candidate"
        findings = self.validator.validate_ledger(ledger, PACKAGE)
        self.assertTrue(any(item["code"] == "file003_premature_advance" for item in findings))

    def test_machine_check_cannot_be_independent_pass(self) -> None:
        ledger = copy.deepcopy(LEDGER)
        ledger["file_candidates"][2]["verification"]["machine_precheck"][
            "not_an_independent_pass"
        ] = False
        findings = self.validator.validate_ledger(ledger, PACKAGE)
        self.assertTrue(any(item["code"] == "machine_check_misrepresented" for item in findings))

    def test_template_findings_cannot_close_early(self) -> None:
        ledger = copy.deepcopy(LEDGER)
        ledger["findings"][6]["issue_state"] = "closed"
        findings = self.validator.validate_ledger(ledger, PACKAGE)
        self.assertTrue(
            any(
                item["code"]
                in {"template_finding_state_mismatch", "closed_without_verification"}
                for item in findings
            )
        )

    def test_file001_pass_must_link_independent_report(self) -> None:
        ledger = copy.deepcopy(LEDGER)
        ledger["file_candidates"][0]["verification"]["result_links"] = []
        findings = self.validator.validate_ledger(ledger, PACKAGE)
        self.assertTrue(any(item["code"] == "missing_independent_result_link" for item in findings))


if __name__ == "__main__":
    unittest.main(verbosity=2)

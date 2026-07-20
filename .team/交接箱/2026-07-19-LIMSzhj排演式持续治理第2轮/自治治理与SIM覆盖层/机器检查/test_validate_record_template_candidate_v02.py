#!/usr/bin/env python3
"""第二轮记录模板逐字段候选 v0.2 回归测试。"""

from __future__ import annotations

import copy
import importlib.util
import json
import unittest
from pathlib import Path


HERE = Path(__file__).resolve().parent
PACKAGE_DIR = HERE.parent
VALIDATOR_PATH = HERE / "validate_record_template_candidate_v02.py"
CANDIDATE_PATH = PACKAGE_DIR / "候选覆盖层" / "SIM-记录模板语义覆盖候选-v0.2.json"


def load_validator():
    spec = importlib.util.spec_from_file_location("record_template_validator", VALIDATOR_PATH)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"无法加载检查器：{VALIDATOR_PATH}")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class RecordTemplateCandidateV02Test(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.validator = load_validator()
        cls.candidate = json.loads(CANDIDATE_PATH.read_text(encoding="utf-8"))

    def test_real_candidate_passes(self) -> None:
        self.assertEqual([], self.validator.validate_candidate(self.candidate, PACKAGE_DIR))

    def test_all_twelve_records_are_present(self) -> None:
        self.assertEqual(12, len(self.candidate["record_candidates"]))
        self.assertGreaterEqual(
            sum(len(record["fields"]) for record in self.candidate["record_candidates"]),
            160,
        )

    def test_missing_field_locator_is_rejected(self) -> None:
        candidate = copy.deepcopy(self.candidate)
        candidate["record_candidates"][0]["fields"][0]["source_locator"] = ""
        findings = self.validator.validate_candidate(candidate, PACKAGE_DIR)
        self.assertTrue(any(item["code"] == "missing_field_attribute" for item in findings))

    def test_duplicate_variant_identity_is_rejected(self) -> None:
        candidate = copy.deepcopy(self.candidate)
        candidate["bg_04_03_variants"][1]["identity_key"] = candidate["bg_04_03_variants"][0][
            "identity_key"
        ]
        findings = self.validator.validate_candidate(candidate, PACKAGE_DIR)
        self.assertTrue(any(item["code"] == "duplicate_variant_identity" for item in findings))

    def test_unsupported_technical_value_is_rejected(self) -> None:
        candidate = copy.deepcopy(self.candidate)
        field = next(
            field
            for record in candidate["record_candidates"]
            for field in record["fields"]
            if field["source_level"] == "evidence_insufficient"
        )
        field["proposed_value"] = "100 ± 1"
        findings = self.validator.validate_candidate(candidate, PACKAGE_DIR)
        self.assertTrue(any(item["code"] == "unsupported_technical_value" for item in findings))

    def test_orphan_preview_requires_zero_current_baseline_instances(self) -> None:
        candidate = copy.deepcopy(self.candidate)
        candidate["orphan_key_migration_preview"]["fresh_8013_baseline_instance_count"] = 1
        findings = self.validator.validate_candidate(candidate, PACKAGE_DIR)
        self.assertTrue(any(item["code"] == "unexpected_baseline_instance_count" for item in findings))


if __name__ == "__main__":
    unittest.main(verbosity=2)

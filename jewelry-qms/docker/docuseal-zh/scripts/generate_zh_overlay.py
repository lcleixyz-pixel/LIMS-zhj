#!/usr/bin/env python3
"""Generate zh/zh-CN overlay files from upstream DocuSeal 3.1.5 i18n sources."""
from __future__ import annotations

import re
import sys
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

from deep_translator import GoogleTranslator

ROOT = Path(__file__).resolve().parents[1]
SCRIPTS = Path(__file__).resolve().parent
OVERLAY = ROOT / "overlay"
CACHE_PATH = SCRIPTS / "zh_cache.json"

# Critical UI overrides
MANUAL = {
    "Sign and Complete": "签名并完成",
    "Complete": "完成",
    "Download": "下载",
    "Clear": "清除",
    "Redraw": "重画",
    "Submit": "提交",
    "Next": "下一步",
    "Continue": "继续",
    "Sign Now": "立即签名",
    "Start Now": "立即开始",
    "Signature": "签名",
    "Initials": "缩写签名",
    "Date": "日期",
    "Email": "邮箱",
    "Decline": "拒绝",
    "Optional": "可选",
    "optional": "可选",
    "Upload": "上传",
    "Language": "语言",
    "Templates": "模板",
    "Submissions": "提交记录",
    "Settings": "设置",
    "Account": "账户",
    "Users": "用户",
    "Save": "保存",
    "Cancel": "取消",
    "Delete": "删除",
    "Edit": "编辑",
    "Create": "创建",
    "Send": "发送",
    "Back": "返回",
    "Close": "关闭",
    "Search": "搜索",
    "Name": "名称",
    "Password": "密码",
    "Documents": "文档",
    "Document": "文档",
    "Status": "状态",
    "Role": "角色",
    "Phone": "电话",
    "Text": "文本",
    "Number": "数字",
    "Checkbox": "复选框",
    "File": "文件",
    "Image": "图片",
    "Stamp": "印章",
    "Processing": "处理中",
    "Submitting": "提交中",
    "English": "英语",
    "English (US)": "英语（美国）",
    "English (UK)": "英语（英国）",
    "English (United States)": "英语（美国）",
    "English (United Kingdom)": "英语（英国）",
    "Step": "步骤",
    "Form progress": "表单进度",
    "Click to upload": "点击上传",
    "or drag and drop files": "或拖放文件",
    "Type here...": "在此输入...",
    "Draw": "手写",
    "Type": "键盘输入",
    "Draw signature": "手写签名",
    "type signature here": "在此输入签名",
    "Type signature here": "在此输入签名",
    "Form has been completed!": "表单已完成！",
    "Document has been signed!": "文档已签署！",
    "Documents have been signed!": "文档已全部签署！",
    "Please fill all required fields": "请填写所有必填项",
    "Set today": "设为今天",
    "Powered by": "技术支持",
}


def log(msg: str) -> None:
    print(msg, flush=True)


def load_cache() -> dict[str, str]:
    import json

    if CACHE_PATH.exists():
        return json.loads(CACHE_PATH.read_text(encoding="utf-8"))
    return {}


def save_cache(cache: dict[str, str]) -> None:
    import json

    CACHE_PATH.write_text(json.dumps(cache, ensure_ascii=False, indent=0), encoding="utf-8")


def translate_one(text: str) -> str:
    if text in MANUAL:
        return MANUAL[text]
    if not re.search(r"[A-Za-z]", text):
        return text
    if text.strip() in {"OK", "API", "QR", "PDF", "SMS", "KBA", "URL", "ID", "OTP", "2FA", "SSO", "CSV", "HTML"}:
        return text
    t = GoogleTranslator(source="en", target="zh-CN")
    for attempt in range(4):
        try:
            out = t.translate(text[:4500])
            return out if out else text
        except Exception as exc:  # noqa: BLE001
            time.sleep(0.4 * (attempt + 1))
            if attempt == 3:
                log(f"WARN failed: {exc!r} :: {text[:60]!r}")
                return text
    return text


def translate_unique(values: list[str], cache: dict[str, str], label: str) -> dict[str, str]:
    todo = [v for v in values if v not in cache and v not in MANUAL]
    # seed manual
    for k, v in MANUAL.items():
        cache[k] = v
    log(f"[{label}] unique={len(set(values))} cached={len(cache)} todo={len(todo)}")
    if not todo:
        return cache
    done = 0
    with ThreadPoolExecutor(max_workers=8) as pool:
        futs = {pool.submit(translate_one, v): v for v in todo}
        for fut in as_completed(futs):
            src = futs[fut]
            cache[src] = fut.result()
            done += 1
            if done == 1 or done % 40 == 0 or done == len(todo):
                log(f"  [{label}] translated {done}/{len(todo)}")
                save_cache(cache)
    save_cache(cache)
    return cache


def extract_js_object(src: str, name: str) -> str:
    m = re.search(rf"const {name} = \{{", src)
    if not m:
        raise SystemExit(f"const {name} = {{ not found")
    start = m.end() - 1
    depth = 0
    i = start
    while i < len(src):
        ch = src[i]
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                return src[start : i + 1]
        i += 1
    raise SystemExit(f"unclosed object {name}")


def parse_js_string_object(obj_src: str) -> dict[str, str]:
    body = obj_src.strip()
    if body.startswith("{"):
        body = body[1:-1]
    result: dict[str, str] = {}
    for m in re.finditer(
        r"(\w+)\s*:\s*(`(?:\\`|[^`])*`|'(?:\\'|[^'])*'|\"(?:\\\"|[^\"])*\")\s*,?",
        body,
        re.S,
    ):
        key = m.group(1)
        lit = m.group(2)
        quote = lit[0]
        inner = lit[1:-1]
        if quote == "'":
            val = (
                inner.replace("\\\\", "\0")
                .replace("\\'", "'")
                .replace('\\"', '"')
                .replace("\\n", "\n")
                .replace("\0", "\\")
            )
        elif quote == '"':
            val = (
                inner.replace("\\\\", "\0")
                .replace('\\"', '"')
                .replace("\\'", "'")
                .replace("\\n", "\n")
                .replace("\0", "\\")
            )
        else:
            val = inner.replace("\\`", "`").replace("\\n", "\n")
        result[key] = val
    return result


def js_escape(s: str) -> str:
    return (
        s.replace("\\", "\\\\")
        .replace("'", "\\'")
        .replace("\n", "\\n")
        .replace("\r", "")
    )


def emit_js_object(name: str, data: dict[str, str]) -> str:
    lines = [f"const {name} = {{"]
    for k, v in data.items():
        lines.append(f"  {k}: '{js_escape(v)}',")
    lines.append("}")
    return "\n".join(lines)


def inject_form_zh(upstream: str, zh: dict[str, str]) -> str:
    zh_block = emit_js_object("zh", zh)
    upstream = upstream.replace(
        "const i18n = { en, es, it, de, fr, pl, uk, cs, pt, he, nl, ar, ko, ja }",
        zh_block + "\n\nconst i18n = { en, es, it, de, fr, pl, uk, cs, pt, he, nl, ar, ko, ja, zh }",
    )
    if "ja, zh }" not in upstream:
        raise SystemExit("failed to inject form zh into i18n export")
    return upstream


def inject_builder_zh(upstream: str, zh: dict[str, str]) -> str:
    zh_block = emit_js_object("zh", zh)
    upstream = re.sub(
        r"export \{ en, es, it, pt, fr, de, nl \}",
        zh_block + "\n\nexport { en, es, it, pt, fr, de, nl, zh }",
        upstream,
    )
    if "nl, zh }" not in upstream:
        raise SystemExit("failed to inject builder zh export")
    return upstream


def yaml_quote(s: str) -> str:
    if s == "":
        return "''"
    needs = any(
        c in s
        for c in [
            ":",
            "#",
            "{",
            "}",
            "[",
            "]",
            ",",
            "&",
            "*",
            "?",
            "|",
            ">",
            "'",
            '"',
            "%",
            "@",
            "`",
            "!",
        ]
    ) or s.strip() != s or "\n" in s or s.lower() in {"true", "false", "null", "yes", "no"}
    if needs or re.match(r"^-?\d", s):
        esc = (
            s.replace("\\", "\\\\")
            .replace('"', '\\"')
            .replace("\n", "\\n")
            .replace("\t", "\\t")
        )
        return f'"{esc}"'
    return s


def extract_en_yaml_map_via_pyyaml(yml_path: Path) -> dict[str, str]:
    import yaml

    with yml_path.open("r", encoding="utf-8") as f:
        doc = yaml.safe_load(f)
    en = doc["en"]
    out: dict[str, str] = {}
    for k, v in en.items():
        if isinstance(v, str):
            out[k] = v
        elif v is None:
            out[k] = ""
        else:
            out[k] = str(v)
    return out


def map_with_cache(en: dict[str, str], cache: dict[str, str]) -> dict[str, str]:
    out: dict[str, str] = {}
    for k, v in en.items():
        if k.startswith("language_") and k not in ("language_en", "language_en-US", "language_en-GB"):
            out[k] = v
            continue
        if k == "language_en":
            out[k] = "英语"
            continue
        if k == "language_en-US":
            out[k] = "英语（美国）"
            continue
        if k == "language_en-GB":
            out[k] = "英语（英国）"
            continue
        out[k] = cache.get(v, MANUAL.get(v, v))
    return out


def build_zh_cn_yaml_block(translated: dict[str, str]) -> str:
    lines = ["zh-CN: &zh-CN", "  <<: *en"]
    for k, v in translated.items():
        lines.append(f"  {k}: {yaml_quote(v)}")
    lines.append("")
    lines.append("zh:")
    lines.append("  <<: *zh-CN")
    return "\n".join(lines) + "\n"


def patch_application_rb(src: str) -> str:
    old = (
        "config.i18n.available_locales = %i[en en-US en-GB es-ES fr-FR pt-PT de-DE it-IT nl-NL\n"
        "                                       es it de fr nl pl uk cs pt he ar ko ja]"
    )
    new = (
        "config.i18n.available_locales = %i[en en-US en-GB es-ES fr-FR pt-PT de-DE it-IT nl-NL zh-CN\n"
        "                                       es it de fr nl pl uk cs pt he ar ko ja zh]"
    )
    if "zh-CN" in src and re.search(r"\bzh\]", src):
        return src
    if old not in src:
        raise SystemExit("application.rb available_locales pattern not found")
    return src.replace(old, new)


def patch_accounts_controller(src: str) -> str:
    needle = "    'nl-NL' => 'Nederlands'\n  }.freeze"
    insert = "    'nl-NL' => 'Nederlands',\n    'zh-CN' => '中文（简体）'\n  }.freeze"
    if "zh-CN" in src and "中文" in src:
        return src
    if needle not in src:
        raise SystemExit("LOCALE_OPTIONS pattern not found")
    return src.replace(needle, insert)


def main() -> None:
    cache = load_cache()
    OVERLAY.mkdir(parents=True, exist_ok=True)

    log("== form i18n ==")
    form_src = (SCRIPTS / "upstream_form_i18n.js").read_text(encoding="utf-8")
    form_en = parse_js_string_object(extract_js_object(form_src, "en"))
    log(f"form keys: {len(form_en)}")
    translate_unique(list(form_en.values()), cache, "form")
    form_zh = map_with_cache(form_en, cache)
    out_form = OVERLAY / "app/javascript/submission_form/i18n.js"
    out_form.parent.mkdir(parents=True, exist_ok=True)
    out_form.write_text(inject_form_zh(form_src, form_zh), encoding="utf-8")

    log("== builder i18n ==")
    builder_src = (SCRIPTS / "upstream_builder_i18n.js").read_text(encoding="utf-8")
    builder_en = parse_js_string_object(extract_js_object(builder_src, "en"))
    log(f"builder keys: {len(builder_en)}")
    translate_unique(list(builder_en.values()), cache, "builder")
    builder_zh = map_with_cache(builder_en, cache)
    out_builder = OVERLAY / "app/javascript/template_builder/i18n.js"
    out_builder.parent.mkdir(parents=True, exist_ok=True)
    out_builder.write_text(inject_builder_zh(builder_src, builder_zh), encoding="utf-8")

    log("== i18n.yml ==")
    en_map = extract_en_yaml_map_via_pyyaml(SCRIPTS / "upstream_i18n.yml")
    log(f"yml en keys: {len(en_map)}")
    translate_unique(list(en_map.values()), cache, "yml")
    yml_zh = map_with_cache(en_map, cache)
    yml_zh["language_zh"] = "中文"
    yml_zh["language_zh-CN"] = "中文（简体）"
    yml_src = (SCRIPTS / "upstream_i18n.yml").read_text(encoding="utf-8")
    if "language_zh:" not in yml_src:
        yml_src = yml_src.replace(
            "  language_ja: 日本語\n",
            "  language_ja: 日本語\n  language_zh: 中文\n  language_zh-CN: 中文（简体）\n",
            1,
        )
    if not yml_src.endswith("\n"):
        yml_src += "\n"
    yml_src = yml_src + "\n" + build_zh_cn_yaml_block(yml_zh)
    out_yml = OVERLAY / "config/locales/i18n.yml"
    out_yml.parent.mkdir(parents=True, exist_ok=True)
    out_yml.write_text(yml_src, encoding="utf-8")

    log("== application.rb / accounts_controller.rb ==")
    out_app = OVERLAY / "config/application.rb"
    out_app.parent.mkdir(parents=True, exist_ok=True)
    out_app.write_text(
        patch_application_rb((SCRIPTS / "upstream_application.rb").read_text(encoding="utf-8")),
        encoding="utf-8",
    )
    out_acc = OVERLAY / "app/controllers/accounts_controller.rb"
    out_acc.parent.mkdir(parents=True, exist_ok=True)
    out_acc.write_text(
        patch_accounts_controller(
            (SCRIPTS / "upstream_accounts_controller.rb").read_text(encoding="utf-8")
        ),
        encoding="utf-8",
    )
    log("DONE")


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        sys.exit(130)

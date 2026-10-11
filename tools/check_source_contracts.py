#!/usr/bin/env python3
"""Narrow signature checks against official target source, not Magento runtime checks."""
from __future__ import annotations
import argparse
import hashlib
import json
import re
from datetime import datetime, timezone
from pathlib import Path

CONTRACTS = [
    ("Stock CSV converter method", "app/code/Magento/Ui/Model/Export/ConvertToCsv.php", r"public\s+function\s+getCsvFile\s*\(\s*\)"),
    ("Stock XML converter method", "app/code/Magento/Ui/Model/Export/ConvertToXml.php", r"public\s+function\s+getXmlFile\s*\(\s*\)"),
    ("MassAction inheritance", "app/code/Magento/Ui/Component/MassAction.php", r"class\s+MassAction\s+extends\s+AbstractComponent"),
    ("MassAction prepare", "app/code/Magento/Ui/Component/MassAction.php", r"public\s+function\s+prepare\s*\(\s*\)"),
    ("UI constructor context/components/data", "app/code/Magento/Ui/Component/AbstractComponent.php", r"public\s+function\s+__construct\s*\(\s*ContextInterface\s+\$context,\s*array\s+\$components\s*=\s*\[\],\s*array\s+\$data\s*=\s*\[\]"),
    ("UI protected components", "app/code/Magento/Ui/Component/AbstractComponent.php", r"protected\s+\$components"),
    ("Selection component lookup", "app/code/Magento/Ui/Component/MassAction/Filter.php", r"public\s+function\s+getComponent\s*\(\s*\)"),
    ("Selection component preparation", "app/code/Magento/Ui/Component/MassAction/Filter.php", r"public\s+function\s+prepareComponent\s*\(UiComponentInterface\s+\$component\)"),
    ("Selection provider application", "app/code/Magento/Ui/Component/MassAction/Filter.php", r"public\s+function\s+applySelectionOnTargetProvider\s*\(\s*\)"),
    ("Selection database collection", "app/code/Magento/Ui/Component/MassAction/Filter.php", r"public\s+function\s+getCollection\s*\(AbstractDb\s+\$collection\)"),
    ("Native selection sends one mode key", "app/code/Magento/Ui/view/base/web/js/grid/massactions.js", r"var\s+itemsType\s*=\s*data\.excludeMode\s*\?\s*'excluded'\s*:\s*'selected'"),
    ("Config field renderer element type", "app/code/Magento/Config/Block/System/Config/Form/Field.php", r"protected\s+function\s+_getElementHtml\s*\(\\Magento\\Framework\\Data\\Form\\Element\\AbstractElement\s+\$element\)"),
    ("Module declaration lookup", "lib/internal/Magento/Framework/Module/ModuleListInterface.php", r"public\s+function\s+getOne\s*\(\$name\)"),
    ("Native product default helper", "app/code/Magento/Catalog/Controller/Adminhtml/Product/Initialization/Helper/AttributeFilter.php", r"public\s+function\s+prepareProductAttributes\s*\(Product\s+\$product,\s*array\s+\$productData,\s*array\s+\$useDefaults\):\s*array"),
    ("Native numeric default sentinel", "app/code/Magento/Catalog/Controller/Adminhtml/Product/Initialization/Helper/AttributeFilter.php", r"\$productData\[\$attributeCode\]\s*=\s*null"),
    ("Modern EAV allowed-empty path", "app/code/Magento/Eav/Model/ResourceModel/UpdateHandler.php", r"\$isAllowedEmptyStringValue\s*=\s*\$attribute->isAllowedEmptyTextValue\(\$newValue\)"),
    ("Mail reply-to API", "lib/internal/Magento/Framework/Mail/Template/TransportBuilder.php", r"public\s+function\s+setReplyTo\s*\(\$email,\s*\$name\s*=\s*null\)"),
    ("Mail scoped sender API", "lib/internal/Magento/Framework/Mail/Template/TransportBuilder.php", r"public\s+function\s+setFromByScope\s*\(\$from,\s*\$scopeId\s*=\s*null\)"),
    ("Mail transport API", "lib/internal/Magento/Framework/Mail/Template/TransportBuilder.php", r"public\s+function\s+getTransport\s*\(\s*\)"),
    ("Inline translation current state", "lib/internal/Magento/Framework/Translate/Inline/StateInterface.php", r"public\s+function\s+isEnabled\s*\(\s*\)"),
    ("Inline translation suspend API", "lib/internal/Magento/Framework/Translate/Inline/StateInterface.php", r"public\s+function\s+suspend\s*\(\$status\s*=\s*false\)"),
    ("Inline translation resume API", "lib/internal/Magento/Framework/Translate/Inline/StateInterface.php", r"public\s+function\s+resume\s*\(\$status\s*=\s*true\)"),
]

def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--tag', required=True)
    parser.add_argument('--source-root', action='append', required=True, type=Path)
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args()
    checks = []
    for label, relative, pattern in CONTRACTS:
        path = next((root / relative for root in args.source_root if (root / relative).is_file()), None)
        if path is None:
            checks.append({'check': label, 'status': 'NOT RUN', 'file': relative})
            continue
        data = path.read_bytes()
        passed = re.search(pattern, data.decode('utf-8'), re.MULTILINE) is not None
        checks.append({'check': label, 'status': 'PASS' if passed else 'FAIL', 'file': relative,
                       'source_url': f'https://raw.githubusercontent.com/magento/magento2/{args.tag}/{relative}',
                       'sha256': hashlib.sha256(data).hexdigest(), 'pattern': pattern})
    totals = {status: sum(row['status'] == status for row in checks) for status in ('PASS', 'FAIL', 'NOT RUN')}
    report = {'generated_at': datetime.now(timezone.utc).isoformat(), 'tag': args.tag,
              'scope': 'Only source signatures/patterns are checked. DI/interception/persistence/runtime NOT RUN.',
              'checks': checks, 'totals': totals}
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(json.dumps(totals))
    for row in checks:
        if row['status'] != 'PASS':
            print(row['status'], row['check'])
    return 1 if totals['FAIL'] else 0

if __name__ == '__main__':
    raise SystemExit(main())

"use strict";

const fs = require("fs");
const path = require("path");

const cmsFile = path.resolve(__dirname, "../../../modules/dbxContent_admin/js/cms-page.js");
const source = fs.readFileSync(cmsFile, "utf8");
const match = source.match(/function cmsFieldValue\([^)]*\) \{[\s\S]*?\n        \}/);

if (!match) {
    throw new Error("cmsFieldValue wurde in cms-page.js nicht gefunden.");
}

const cmsFieldValue = Function(`"use strict"; return (${match[0]});`)();
const cases = [
    [0, 0, "Numerische Null muss erhalten bleiben"],
    ["0", "0", "Statuswert 0 muss erhalten bleiben"],
    [1, 1, "Numerische Eins muss erhalten bleiben"],
    ["1", "1", "Statuswert 1 muss erhalten bleiben"],
    [null, "", "null wird als leeres Feld dargestellt"],
    [undefined, "", "undefined wird als leeres Feld dargestellt"],
];

for (const [input, expected, label] of cases) {
    const actual = cmsFieldValue(input);
    if (actual !== expected) {
        throw new Error(`${label}: erwartet ${String(expected)}, erhalten ${String(actual)}`);
    }
}

console.log("OK cms_zero_field_value_test");

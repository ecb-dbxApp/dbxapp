"use strict";

const fs = require("fs");
const path = require("path");

const cmsFile = path.resolve(__dirname, "../../../js/lib/cms.js");
const source = fs.readFileSync(cmsFile, "utf8");
const match = source.match(/function mediaSlotMatchesBox\([^)]*\) \{[\s\S]*?\n    \}/);

if (!match) {
    throw new Error("mediaSlotMatchesBox wurde in cms.js nicht gefunden.");
}

const mediaSlotMatchesBox = Function(`"use strict"; return (${match[0]});`)();
const cases = [
    ["gallery", "gallery", "all", true, "Gallery-Zuordnung in Gallery"],
    ["gallery", "shop", "all", true, "Shop-Zuordnung in Gallery"],
    ["gallery", "inline", "all", false, "Inline-Zuordnung nicht in Gallery"],
    ["inline", "shop", "all", false, "Shop-Zuordnung nicht im Text"],
    ["shop", "shop", "all", true, "Shop-Zuordnung in eigenem Shop-Bereich"],
    ["all", "shop", "gallery", false, "Gallery-Filter bleibt im Medienbrowser exakt"],
    ["all", "shop", "all", true, "Alle zeigt Shop-Zuordnung"],
    ["custom", "hero", "all", false, "Hero bleibt aus normalen Listen ausgeblendet"],
];

for (const [boxSlot, slot, filter, expected, label] of cases) {
    const actual = mediaSlotMatchesBox(boxSlot, slot, filter);
    if (actual !== expected) {
        throw new Error(`${label}: erwartet ${expected}, erhalten ${actual}`);
    }
}

console.log("OK cms_shop_gallery_test");

/** @type {import("stylelint").Config} */
export default {
    "extends": ["stylelint-config-standard"],
    "rules": {
        "selector-class-pattern": "^[A-Z][a-zA-Z0-9]*(__[a-z][a-zA-Z0-9]*)?(--[a-z][a-zA-Z0-9]*)?$",
        // BEM state modifiers (.X--open vs .X:not(--open)) trip this with equal-specificity false positives.
        "no-descending-specificity": null,
        // Keep -webkit-mask-* (alongside standard mask-*) for iOS Safari <15.4.
        "property-no-vendor-prefix": null,
    }
};

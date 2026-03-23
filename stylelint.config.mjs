/** @type {import("stylelint").Config} */
export default {
    "extends": ["stylelint-config-standard"],
    "rules": {
        "selector-class-pattern": "^[A-Z][a-zA-Z0-9]*(__[a-z][a-zA-Z0-9]*)?(--[a-z][a-zA-Z0-9]*)?$",
    }
};

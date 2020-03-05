"use strict";

module.exports = [{
  names: ["CHANGELOG-RULE-001"],
  description: "Version header format",
  tags: ["headings", "headers", "changelog"],
  function: (params, onError) => {
    params.tokens.filter(function filterToken(token) {
      return token.type === "heading_open";
    }).forEach(function forToken(token) {
      if (token.tag === "h2") {
        if (/^## [vV]?[\[]?\d+\.\d+\.\d+(-[0-9A-Za-z-.]+|)[\]]?$/m.test(token.line)) {
          return;
        }

        if (/^## [vV]?[\[]?\d+\.\d+\.\d+(-[0-9A-Za-z-.]+|)[\]]? - 20[12][0-9]-[01][0-9]-[0-3][0-9]$/m.test(token.line)) {
          return;
        }

        if (/^## [\[]?unreleased[\]]?$/mi.test(token.line)) {
          return;
        }

        return onError({
          lineNumber: token.lineNumber,
          detail: "Allowed formats:\n-'[vX.X.X(-pre.release)]'\n-'vX.X.X(-pre.release)'\n-'vX.X.X(-pre.release) - YYYY-MM-DD'\n-'UNRELEASED'\n-'[unreleased]'",
          context: token.line
        });
      }
    });
  }
}, {
  names: ["CHANGELOG-RULE-002"],
  description: "Type of changes format",
  tags: ["headings", "headers", "changelog"],
  function: (params, onError) => {
    params.tokens.filter(function filterToken(token) {
      return token.type === "heading_open";
    }).forEach(function forToken(token) {
      if (token.tag === "h3") {
        if (/^### (Added|Changed|Deprecated|Removed|Fixed|Security)$/m.test(token.line)) {
          return;
        }

        return onError({
          lineNumber: token.lineNumber,
          detail: "Allowed types is: Added, Changed, Deprecated, Removed, Fixed or Security",
          context: token.line
        });
      }
    });
  }
}, {
  names: ["CHANGELOG-RULE-003"],
  description: "The list items must be without punctuation marks at the end",
  tags: ["lists", "changelog"],
  function: (params, onError) => {
    params.tokens.filter(function filterToken(token) {
      return token.type === "list_item_open";
    }).forEach(function forToken(token) {
      if (token.tag === "li") {
        if (/[;,\.]$/m.test(token.line)) {
          return onError({
            lineNumber: token.lineNumber,
            detail: "'.', ';' or ',' at the end of list entry",
            context: token.line
          });
        }
      }
    });
  }
}];

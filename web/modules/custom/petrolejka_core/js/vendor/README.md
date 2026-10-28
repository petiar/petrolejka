# Externé JS knižnice (bez build kroku)

- `bysquare-pay-4.1.0.mjs`: [bysquare](https://github.com/xseman/bysquare) 4.1.0, modul `pay`, licencia Apache-2.0.
  Je to samostatný ESM bundle vrátane závislostí (lzma1, validator) z `https://esm.sh/bysquare@4.1.0/es2020/pay.bundle.mjs`.
- `qrcode-generator-2.0.4.mjs`: [qrcode-generator](https://github.com/kazuhikoarase/qrcode-generator) 2.0.4,
  licencia MIT, súbor `dist/qrcode.mjs` z npm.

Pri aktualizácii zmeň číslo verzie v názve súboru (kvôli cache na proxy) aj import v `../donation.js`.

/**
 * @file
 * Darovanie: vygeneruje PAY by square QR kód zo sumy zadanej návštevníkom.
 *
 * Údaje o účte prichádzajú v drupalSettings.petrolejkaDonation (nastavenia
 * v /admin/config/services/darovanie). Nič sa neodosiela na server.
 */
import { encode, PaymentOptions, CurrencyCode, Periodicity } from './vendor/bysquare-pay-4.1.0.mjs';
import qrcode from './vendor/qrcode-generator-2.0.4.mjs';

const { Drupal, drupalSettings, once } = window;

// Verzia štandardu v hlavičke QR: 0 = 1.0.0. bysquare 4.x predvolene píše
// 1.2.0, ktorú bankové aplikácie nerozpoznajú (QR kód vôbec nenačítajú).
// Obsah je pre všetky verzie rovnaký, mení sa len hlavička.
const SPEC_VERSION = 0;

const formatAmount = (amount) => new Intl.NumberFormat('sk-SK', { style: 'currency', currency: 'EUR' }).format(amount);

/**
 * Najbližší dátum s daným dňom v mesiaci, najskôr zajtra.
 */
function nextMonthlyDate(day) {
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  const date = new Date(tomorrow.getFullYear(), tomorrow.getMonth(), day);
  if (date < new Date(tomorrow.getFullYear(), tomorrow.getMonth(), tomorrow.getDate())) {
    date.setMonth(date.getMonth() + 1);
  }
  return date;
}

const ymd = (d) => `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}${String(d.getDate()).padStart(2, '0')}`;

function buildPayment(config, amount, name, monthly) {
  const note = [config.note, name].filter(Boolean).join(' - ').slice(0, 140);
  const payment = {
    type: monthly ? PaymentOptions.StandingOrder : PaymentOptions.PaymentOrder,
    amount,
    currencyCode: CurrencyCode.EUR,
    paymentNote: note,
    beneficiary: { name: config.beneficiary },
    bankAccounts: [config.bic ? { iban: config.iban, bic: config.bic } : { iban: config.iban }],
  };
  if (config.variableSymbol) {
    payment.variableSymbol = config.variableSymbol;
  }
  if (config.constantSymbol) {
    payment.constantSymbol = config.constantSymbol;
  }
  let firstDate = null;
  if (monthly) {
    // Trvalý príkaz: každý mesiac v nastavený deň, od najbližšieho takého dňa.
    firstDate = nextMonthlyDate(config.monthlyDay);
    payment.periodicity = Periodicity.Monthly;
    payment.day = config.monthlyDay;
    payment.paymentDueDate = ymd(firstDate);
  }
  return { payment, note, firstDate };
}

/**
 * Vykreslí QR kód ako SVG a vráti PNG (data URL) na stiahnutie.
 *
 * PNG preto, lebo bankové aplikácie vedia načítať QR z obrázka v galérii,
 * ale SVG väčšinou nie.
 */
function renderQr(container, data) {
  const qr = qrcode(0, 'M');
  qr.addData(data);
  qr.make();
  container.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 4, scalable: true });
  // Obsah QR kódu (na kontrolu a testy).
  container.dataset.payload = data;
  const svg = container.querySelector('svg');
  svg.setAttribute('role', 'img');
  svg.setAttribute('aria-label', Drupal.t('PAY by square QR kód pre platbu'));

  const count = qr.getModuleCount();
  const cell = 12;
  const margin = 4 * cell;
  const canvas = document.createElement('canvas');
  canvas.width = canvas.height = count * cell + 2 * margin;
  const ctx = canvas.getContext('2d');
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.fillStyle = '#000000';
  for (let row = 0; row < count; row++) {
    for (let col = 0; col < count; col++) {
      if (qr.isDark(row, col)) {
        ctx.fillRect(margin + col * cell, margin + row * cell, cell, cell);
      }
    }
  }
  return canvas.toDataURL('image/png');
}

function attach(root) {
  const config = drupalSettings.petrolejkaDonation;
  const form = root.querySelector('[data-donation-form]');
  const amountInput = root.querySelector('[data-donation-amount]');
  const nameInput = root.querySelector('[data-donation-name]');
  const error = root.querySelector('[data-donation-error]');
  const result = root.querySelector('[data-donation-result]');
  const presets = root.querySelectorAll('[data-amount]');

  const selectPreset = (button) => {
    presets.forEach((b) => b.classList.toggle('is-selected', b === button));
  };
  presets.forEach((button) => {
    button.addEventListener('click', () => {
      amountInput.value = button.dataset.amount;
      selectPreset(button);
      form.requestSubmit();
    });
  });
  amountInput.addEventListener('input', () => selectPreset(null));

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const amount = Math.round(parseFloat(String(amountInput.value).replace(',', '.')) * 100) / 100;
    if (!(amount >= 1 && amount <= 10000)) {
      error.textContent = Drupal.t('Zadajte sumu od 1 do 10 000 €.');
      error.hidden = false;
      result.hidden = true;
      amountInput.focus();
      return;
    }
    error.hidden = true;

    // Diakritiku zo správy odstráni encode() (predvolené deburr), lebo
    // banky ju nie vždy zvládnu.
    const frequency = root.querySelector('[data-donation-frequency]:checked');
    const monthly = Boolean(config.monthlyDay) && frequency?.value === 'monthly';
    const { payment, note, firstDate } = buildPayment(config, amount, nameInput.value.trim(), monthly);
    let data;
    try {
      data = encode({ payments: [payment] }, { deburr: true, validate: true, version: SPEC_VERSION });
    }
    catch (e) {
      error.textContent = Drupal.t('QR kód sa nepodarilo vytvoriť. Použite prosím údaje na platbu nižšie.');
      error.hidden = false;
      return;
    }

    const png = renderQr(root.querySelector('[data-donation-qr]'), data);
    root.querySelector('[data-donation-out="amount"]').textContent = formatAmount(amount) + (monthly ? Drupal.t(' mesačne') : '');
    root.querySelector('[data-donation-out="note"]').textContent = note;
    root.querySelectorAll('[data-donation-monthly-row]').forEach((el) => { el.hidden = !monthly; });
    if (monthly) {
      root.querySelector('[data-donation-out="schedule"]').textContent = Drupal.t('každý mesiac @day. dňa, prvá platba @date', {
        '@day': config.monthlyDay,
        '@date': firstDate.toLocaleDateString('sk-SK'),
      });
    }
    root.querySelector('[data-donation-download]').href = png;
    result.hidden = false;
    result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });

  const copy = root.querySelector('[data-donation-copy]');
  copy.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(config.iban);
      copy.textContent = Drupal.t('Skopírované');
    }
    catch (e) {
      copy.textContent = config.iban;
    }
  });
}

Drupal.behaviors.petrolejkaDonation = {
  attach(context) {
    once('petrolejka-donation', '[data-donation]', context).forEach(attach);
  },
};

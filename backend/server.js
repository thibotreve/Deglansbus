require('dotenv').config();
const express = require('express');
const session = require('express-session');
const rateLimit = require('express-rate-limit');
const nodemailer = require('nodemailer');
const path = require('path');
const fs = require('fs');

const app = express();
const PORT = process.env.PORT || 3000;

// ── JSON DATABASE ─────────────────────────────────────────────────────────────
const dbPath = path.join(__dirname, 'data', 'leads.json');
fs.mkdirSync(path.dirname(dbPath), { recursive: true });

function laadLeads() {
  if (!fs.existsSync(dbPath)) return [];
  try { return JSON.parse(fs.readFileSync(dbPath, 'utf8')); } catch { return []; }
}

function slaLeadsOp(leads) {
  fs.writeFileSync(dbPath, JSON.stringify(leads, null, 2), 'utf8');
}

function nieuweLead(data) {
  const leads = laadLeads();
  const lead = {
    id: (leads.length > 0 ? Math.max(...leads.map(l => l.id)) : 0) + 1,
    ...data,
    gelezen: false,
    aangemaakt_op: new Date().toLocaleString('nl-BE', { timeZone: 'Europe/Brussels' }),
  };
  leads.unshift(lead);
  slaLeadsOp(leads);
  return lead;
}

// ── MIDDLEWARE ────────────────────────────────────────────────────────────────
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

app.use(session({
  secret: process.env.SESSION_SECRET || 'glansbus-geheim-sleutel',
  resave: false,
  saveUninitialized: false,
  cookie: { maxAge: 8 * 60 * 60 * 1000 }, // 8 uur
}));

app.use((req, res, next) => {
  const origin = process.env.FRONTEND_URL || '*';
  res.setHeader('Access-Control-Allow-Origin', origin);
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  if (req.method === 'OPTIONS') return res.sendStatus(200);
  next();
});

// ── RATE LIMITERS ─────────────────────────────────────────────────────────────
const contactLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 3,
  message: { success: false, error: 'Te veel verzoeken. Probeer opnieuw over 15 minuten.' },
  standardHeaders: true,
  legacyHeaders: false,
});

const loginLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 10,
  message: { success: false, error: 'Te veel inlogpogingen. Probeer later opnieuw.' },
  standardHeaders: true,
  legacyHeaders: false,
});

// ── EMAIL ─────────────────────────────────────────────────────────────────────
const transporter = nodemailer.createTransport({
  service: 'gmail',
  auth: {
    user: process.env.GMAIL_USER,
    pass: process.env.GMAIL_APP_PASSWORD,
  },
});

const DIENST_LABELS = {
  exterior:   'Exterior Detail – €69',
  prowash:    'Glansbus Pro Wash – €99',
  fulldetail: 'Glansbus Full Detail – Vanaf €129',
  andere:     'Andere / Vraag',
};

async function stuurNotificatie(lead) {
  const dienstTekst = DIENST_LABELS[lead.dienst] || lead.dienst || '–';
  await transporter.sendMail({
    from: `"De Glansbus Website" <${process.env.GMAIL_USER}>`,
    to: process.env.NOTIFICATIE_EMAIL,
    subject: `Nieuwe aanvraag van ${lead.naam}`,
    html: `
      <div style="font-family:Inter,sans-serif;max-width:580px;color:#1a1c1e;">
        <div style="background:#1a1c1e;padding:24px 32px;border-radius:12px 12px 0 0;">
          <h2 style="color:#fff;margin:0;font-size:1.1rem;font-weight:700;">Nieuwe contactaanvraag</h2>
        </div>
        <div style="border:1px solid #e5e7eb;border-top:none;padding:32px;border-radius:0 0 12px 12px;">
          <table style="width:100%;border-collapse:collapse;font-size:0.95rem;">
            <tr><td style="padding:10px 0;color:#6b7280;width:110px;vertical-align:top;">Naam</td><td style="padding:10px 0;font-weight:600;">${lead.naam}</td></tr>
            <tr style="border-top:1px solid #f3f4f6;"><td style="padding:10px 0;color:#6b7280;vertical-align:top;">E-mail</td><td style="padding:10px 0;"><a href="mailto:${lead.email}" style="color:#1a1c1e;">${lead.email}</a></td></tr>
            <tr style="border-top:1px solid #f3f4f6;"><td style="padding:10px 0;color:#6b7280;vertical-align:top;">Telefoon</td><td style="padding:10px 0;">${lead.telefoon || '–'}</td></tr>
            <tr style="border-top:1px solid #f3f4f6;"><td style="padding:10px 0;color:#6b7280;vertical-align:top;">Dienst</td><td style="padding:10px 0;">${dienstTekst}</td></tr>
            <tr style="border-top:1px solid #f3f4f6;"><td style="padding:10px 0;color:#6b7280;vertical-align:top;">Bericht</td><td style="padding:10px 0;">${(lead.bericht || '–').replace(/\n/g, '<br>').replace(/</g, '&lt;')}</td></tr>
          </table>
          <div style="margin-top:24px;padding-top:16px;border-top:1px solid #f3f4f6;color:#9ca3af;font-size:0.8rem;">
            Lead #${lead.id} · ${lead.aangemaakt_op} · IP: ${lead.ip}
          </div>
        </div>
      </div>
    `,
  });
}

// ── FRONTEND STATIC FILES ─────────────────────────────────────────────────────
const SITE = path.join(__dirname, '..');
['/'].forEach(r => app.get(r, (req, res) => res.sendFile(path.join(SITE, 'index.html'))));
app.get('/index.html',    (req, res) => res.sendFile(path.join(SITE, 'index.html')));
app.get('/contact.html',  (req, res) => res.sendFile(path.join(SITE, 'contact.html')));
app.get('/diensten.html', (req, res) => res.sendFile(path.join(SITE, 'diensten.html')));
app.get('/style.css',     (req, res) => res.sendFile(path.join(SITE, 'style.css')));
// images/ (lowercase) en Images/ (Windows upload) beide afhandelen
app.use('/images', express.static(path.join(SITE, 'images')));
app.use('/images', express.static(path.join(SITE, 'Images')));

// ── ADMIN AUTH MIDDLEWARE ─────────────────────────────────────────────────────
function adminAuth(req, res, next) {
  if (req.session && req.session.ingelogd) return next();
  res.redirect('/admin/login');
}

// ── ROUTE: LOGIN ──────────────────────────────────────────────────────────────
app.get('/admin/login', (req, res) => {
  if (req.session && req.session.ingelogd) return res.redirect('/admin');
  res.sendFile(path.join(__dirname, 'admin', 'login.html'));
});

app.post('/admin/login', loginLimiter, (req, res) => {
  const { gebruiker, wachtwoord } = req.body;
  const correctUser = process.env.ADMIN_USER || 'vedran';
  const correctPass = process.env.ADMIN_PASSWORD || 'GlansDeBus2026+';
  if (gebruiker === correctUser && wachtwoord === correctPass) {
    req.session.ingelogd = true;
    return res.redirect('/admin');
  }
  res.redirect('/admin/login?fout=1');
});

app.post('/admin/uitloggen', (req, res) => {
  req.session.destroy(() => res.redirect('/admin/login'));
});

// ── ROUTES: ADMIN (beschermd) ─────────────────────────────────────────────────
app.use('/admin', adminAuth, express.static(path.join(__dirname, 'admin')));

app.get('/api/admin/leads', adminAuth, (req, res) => {
  res.json(laadLeads());
});

app.post('/api/admin/leads/:id/gelezen', adminAuth, (req, res) => {
  const id = parseInt(req.params.id, 10);
  const leads = laadLeads();
  const lead = leads.find(l => l.id === id);
  if (lead) lead.gelezen = true;
  slaLeadsOp(leads);
  res.json({ success: true });
});

app.delete('/api/admin/leads/:id', adminAuth, (req, res) => {
  const id = parseInt(req.params.id, 10);
  slaLeadsOp(laadLeads().filter(l => l.id !== id));
  res.json({ success: true });
});

// ── ROUTE: CONTACT ────────────────────────────────────────────────────────────
app.post('/api/contact', contactLimiter, async (req, res) => {
  const { naam, email, telefoon, dienst, bericht, _honing, _start } = req.body;

  if (_honing && _honing.length > 0) return res.json({ success: true });
  if (_start && Date.now() - parseInt(_start, 10) < 2000) return res.json({ success: true });

  if (!naam?.trim() || !email?.trim()) {
    return res.status(400).json({ success: false, error: 'Naam en e-mail zijn verplicht.' });
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
    return res.status(400).json({ success: false, error: 'Ongeldig e-mailadres.' });
  }
  if (naam.trim().length > 100 || email.trim().length > 200) {
    return res.status(400).json({ success: false, error: 'Invoer te lang.' });
  }

  const ip = (req.headers['x-forwarded-for'] || req.socket.remoteAddress || '').split(',')[0].trim();

  const lead = nieuweLead({
    naam: naam.trim(),
    email: email.trim(),
    telefoon: telefoon?.trim() || '',
    dienst: dienst || '',
    bericht: bericht?.trim() || '',
    ip,
  });

  try {
    await stuurNotificatie(lead);
  } catch (err) {
    console.error('E-mail mislukt:', err.message);
  }

  res.json({ success: true, message: 'Bedankt! We nemen zo snel mogelijk contact op.' });
});

// ── START ─────────────────────────────────────────────────────────────────────
app.listen(PORT, () => {
  console.log(`Backend: http://localhost:${PORT}`);
  console.log(`Admin:   http://localhost:${PORT}/admin`);
});

# JJ Entertainments — Candy Floss Machine Hire Website

A fun, colourful, mobile-friendly website for a candy floss machine hire business.
It's a single page with a booking enquiry form that emails you each enquiry.

No build tools, no frameworks, no signups — just upload the files to Host Papa.

---

## 📁 What's in here

| File / folder       | What it does                                                        |
|---------------------|---------------------------------------------------------------------|
| `index.html`        | The whole website (hero, services, pricing, gallery, booking form)  |
| `thank-you.html`    | The "thanks!" page shown after someone sends an enquiry             |
| `contact.php`       | Receives the form and emails the enquiry to you                     |
| `css/styles.css`    | All the styling (colours, layout). Edit colours at the very top     |
| `js/main.js`        | Menu, form checks, footer year                                      |
| `images/`           | The logo and candy floss pictures (SVG placeholders)                |
| `README.md`         | This file                                                           |

---

## ✏️ Before you upload — 3 quick edits

### 1. Set where booking emails go (REQUIRED — done on the server)
Your email address is kept **out of the public GitHub repo** for privacy.
`contact.php` reads it from a file called `config.local.php` that lives **only
on the server**.

On the server (cPanel File Manager → the `jjentertainments.co.uk` folder):
1. Copy **`config.example.php`** → rename the copy to **`config.local.php`**.
2. Edit `config.local.php` and fill in your real details:

```php
return array(
    'recipient' => 'you@example.com',                 // where bookings are emailed
    'from'      => 'bookings@jjentertainments.co.uk', // a mailbox at YOUR domain
    'from_name' => 'JJ Entertainments Website',
);
```

- **`recipient`** is where enquiries are sent.
- **`from`** should ideally be a real mailbox at *your own domain* (create one in
  cPanel → **Email Accounts**). Using a Gmail/Outlook address here often makes the
  email land in spam.

`config.local.php` is git-ignored and is never overwritten by the deploy
workflow, so your email stays private and survives every deploy.

### 2. Fill in your own words and details
Search `index.html` for **`EDIT ME`** — those comments mark the spots to personalise:
- Your town / area (About section and footer)
- Your prices (Pricing section — replace the `£XX` placeholders)
- Your phone, email and social links (footer)

### 3. Add real photos (optional, do anytime)
The gallery and hero use simple placeholder graphics. To use your own photos,
drop them in the `images/` folder and update the picture references in
`index.html` (look for the `gallery-item` blocks and the `EDIT ME` notes).

---

## 🚀 Uploading to Host Papa (SFTP)

You'll need an SFTP client. **[FileZilla](https://filezilla-project.org/)** is free and easy.

1. **Get your SFTP details** from Host Papa:
   - Log in to your Host Papa dashboard → your hosting → look for **SFTP/FTP**
     or **FTP Accounts**. You need: **Host**, **Username**, **Password**, and **Port**
     (SFTP is usually port **22**).

2. **Connect in FileZilla:**
   - Host: `sftp://your-host-papa-server` (use the address Host Papa gives you)
   - Username / Password: from step 1
   - Port: `22`
   - Click **Quickconnect**.

3. **Find your website folder** on the server (right-hand panel).
   It's usually called **`public_html`** (or sometimes a folder named after your
   domain). This is the web root — whatever goes in here is your live site.

4. **Upload the files:**
   - In the left-hand panel, open this project folder.
   - Select **everything inside it** (`index.html`, `thank-you.html`,
     `contact.php`, and the `css`, `js`, `images` folders).
   - Drag them into `public_html` on the right.
   - ⚠️ Upload the **contents** of this folder, not the folder itself, so that
     `index.html` ends up directly inside `public_html`.

5. **Visit your website** (`https://yourdomain.com`) — done! 🎉

---

## 🤖 Automatic deploys (GitHub Actions)

This repo includes `.github/workflows/deploy.yml`, which **uploads the site to
Host Papa automatically every time you push to `main`** (over FTPS).

**One-time setup** — add your FTP login as repository secrets:

1. On GitHub: **Settings → Secrets and variables → Actions → New repository secret.**
2. Add three secrets:
   - `FTP_SERVER` — your FTP hostname (from cPanel → **FTP Accounts → Configure
     FTP Client**). Often the server hostname; using that avoids TLS errors.
   - `FTP_USERNAME` — the FTP account's full username.
   - `FTP_PASSWORD` — that account's password.

**Recommended:** create a **dedicated FTP account** in cPanel (**FTP Accounts**)
whose Directory is set to `jjentertainments.co.uk`. Then it can only ever write
to this site's folder, and the workflow's `server-dir: ./` is correct. (If you
instead use the main cPanel FTP account, change `server-dir` in `deploy.yml` to
`jjentertainments.co.uk/`.)

After the secrets are set, every `git push` to `main` deploys the changes.
You can also run it on demand from the repo's **Actions** tab.

> First deploy only: remember `config.local.php` is **not** part of the repo, so
> create it once on the server (see edit #1 above). It won't be touched by future
> deploys.

## ✅ Testing the booking form

1. Go to your live site and scroll to **Book your candy floss**.
2. Fill it in and submit.
3. You should land on the **Thank you** page, and the enquiry email should
   arrive at your `$RECIPIENT` address.
4. **Check your spam folder** the first time. If it's in spam, make sure
   `$FROM_ADDRESS` is a real mailbox at your domain (see edit #1 above).

> The form only works on the live server (or a PHP server) because it needs PHP.
> Opening `index.html` directly from your computer shows the site fine, but the
> form won't send until it's on Host Papa.

### Optional: test on your own computer first
If you have PHP installed, run this in the project folder and open
`http://localhost:8000`:

```
php -S localhost:8000
```

---

## 🆘 If emails don't arrive

PHP email on shared hosting can occasionally be fussy. If you don't get
enquiries, you can switch the form to a free email-forwarding service in under
a minute — **no rebuild needed**:

1. Sign up at a form service such as **[Formspree](https://formspree.io/)** and
   get your form endpoint URL.
2. In `index.html`, change the form's opening tag from:
   ```html
   <form class="booking-form" action="contact.php" method="POST" ...>
   ```
   to your Formspree URL:
   ```html
   <form class="booking-form" action="https://formspree.io/f/yourID" method="POST" ...>
   ```
That's it — enquiries will then be delivered by Formspree instead.

---

Made with 🍭 for JJ Entertainments.

# Serverga o'rnatish (Google Cloud, ~15 daqiqa)

Natija: ikkala kompyuterdan (va telefondan) bitta manzil orqali ishlaysiz. Masalan
`https://34-56-78-90.sslip.io`, parol bilan himoyalangan. Telegram bot 24/7 ishlaydi. GitHub'ga
push qilingan kod serverga 5 daqiqada o'zi tushadi.

**Narxi:** e2-micro server Google'ning doimiy bepul tarifida (us-central1). Tashqi IP ~3-4 $/oy
va Vertex AI $300 trial kreditdan to'lanadi.

## 1. Serverni yaratish

1. https://console.cloud.google.com ni oching va o'ng yuqoridagi **`>_`** (Cloud Shell) tugmasini bosing.
2. Pastda terminal ochiladi. Quyidagini to'liq nusxalab, joylang va Enter bosing:

```bash
gcloud config set project project-990aebdb-5252-4043-862
gcloud services enable compute.googleapis.com aiplatform.googleapis.com
PROJECT=$(gcloud config get-value project)
SA=$(gcloud iam service-accounts list --filter="email~compute@developer" --format="value(email)")
gcloud projects add-iam-policy-binding $PROJECT --member="serviceAccount:$SA" --role="roles/aiplatform.user" --condition=None -q > /dev/null
gcloud compute addresses create maryam-ip --region=us-central1
gcloud compute instances create maryam --zone=us-central1-a --machine-type=e2-micro \
  --image-family=ubuntu-2404-lts-amd64 --image-project=ubuntu-os-cloud \
  --boot-disk-size=30GB --boot-disk-type=pd-standard --address=maryam-ip \
  --service-account=$SA --scopes=cloud-platform --tags=maryam-web
gcloud compute firewall-rules create maryam-web --allow=tcp:80,tcp:443 --target-tags=maryam-web
```

## 2. Loyihani o'rnatish

Cloud Shell'da serverga kiring (birinchi safar kalit so'raydi, Enter bosib o'tavering):

```bash
gcloud compute ssh maryam --zone=us-central1-a
```

Server ichida bitta buyruq:

```bash
curl -fsSL https://raw.githubusercontent.com/aslitukhtaev/maryamagentlar/claude/quirky-bardeen-xnme2p/deploy/setup.sh | sudo bash
```

Skript Telegram bot tokenini so'raydi (hozir bo'lmasa, Enter bosib o'tkazib yuboring). Oxirida
**manzil** va **parol** chiqadi. Ularni saqlab qo'ying.

AI ishlashini tekshirish:

```bash
sudo -u maryam php /opt/maryamagentlar/bin/test-vertex.php
```

## 3. Muhim

- **Kompyuterlaringizdagi botni o'chiring** (`php bin/bot.php` oynasini yoping). Telegram bitta botni
  faqat bitta joyda ishlatishga ruxsat beradi, endi bot serverda ishlaydi.
- **Ma'lumotlar (shablonlar, qoidalar, katalog, baholar) serverda saqlanadi.** Endi faqat server
  manzilidan foydalaning. Kompyuterlardagi eski baza bilan serverdagisi alohida.
- **Kod yangilanishi:** istalgan kompyuterdan GitHub'ga push qilsangiz, server 5 daqiqada o'zi yangilanadi.

## Kerakli buyruqlar (serverda)

| Nima | Buyruq |
|---|---|
| Sozlamalarni o'zgartirish (token, parol) | `sudo nano /opt/maryamagentlar/.env` |
| Botni yoqish / qayta ishga tushirish | `sudo systemctl enable --now maryam-bot` / `sudo systemctl restart maryam-bot` |
| Bot loglari | `journalctl -u maryam-bot -f` |
| Hozir yangilash | `sudo bash /opt/maryamagentlar/deploy/update.sh` |
| Bazaning zaxira nusxasi | `sudo cp /opt/maryamagentlar/data/maryam.db ~/maryam-$(date +%F).db` |

Parolni o'zgartirsangiz (`WEB_PASSWORD=`), darhol kuchga kiradi. Qayta ishga tushirish kerak emas.

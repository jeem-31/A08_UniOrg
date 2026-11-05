<x-mail::message>
# 🎓 Configure Your Organization Platform

Hello **{{ $userName }}**,

Great news! Your organization **{{ $organizationName }}** has been approved and is ready to be configured!

You can now set up your organization's platform by selecting the modules you need and customizing your theme colors.

<x-mail::button :url="$configurationUrl" color="primary">
🚀 Configure Platform Now
</x-mail::button>

---

## 📋 What You'll Configure:

**Modules Selection:**
- Choose from optional modules like Elections, E-Library, EduShare, Complaints, and Documents
- Permanent modules (Announcements, Events, Payments, Chat/FAQs) are always enabled

**Theme Customization:**
- Set your organization's primary color
- Choose a complementary accent color
- Create a unique visual identity for your organization

---

## ⚠️ Important Information:

- ⏰ **Expiration:** This link will expire on **{{ $expirationTime }}**
- 🔒 **One-time use:** The link can only be used once
- ✅ **Auto-activation:** After configuration, your platform will be automatically activated
- 📧 **Officer accounts:** All officers will receive their login credentials after configuration

---

## 💡 Need Help?

If you have any questions or need assistance with the configuration process, please contact the OSAS office.

---

Best regards,<br>
**UniOrg Team**<br>
*University Organization Management Platform*

<x-mail::subcopy>
If you're having trouble clicking the "Configure Platform Now" button, copy and paste the URL below into your web browser:

[{{ $configurationUrl }}]({{ $configurationUrl }})
</x-mail::subcopy>
</x-mail::message>

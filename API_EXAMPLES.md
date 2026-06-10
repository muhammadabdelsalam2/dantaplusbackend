# DentaPlus Insurance Claims System - API Examples

## 🔐 معلومات المصادقة

جميع الـ requests تتطلب:
```
Authorization: Bearer {sanctum_token}
Content-Type: application/json
```

---

## 1️⃣ البحث عن المرضى (Patient Lookup)

### Endpoint
```
GET /api/clinic/insurance/patients/lookup
```

### الإذن المطلوب
```
Permissions: insurance.view|patients.view
```

### أمثلة الطلبات

#### البحث برقم المريض
```bash
curl -X GET "https://api.dentaplus.local/api/clinic/insurance/patients/lookup?patient_number=PAT-2024-001" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

#### البحث برقم المريض الداخلي
```bash
curl -X GET "https://api.dentaplus.local/api/clinic/insurance/patients/lookup?patient_id=42" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

#### البحث بنص حر (الاسم أو الهاتف أو رقم التأمين)
```bash
curl -X GET "https://api.dentaplus.local/api/clinic/insurance/patients/lookup?query=محمد" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

#### البحث مع تحديد عدد النتائج
```bash
curl -X GET "https://api.dentaplus.local/api/clinic/insurance/patients/lookup?query=محمد&limit=20" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

### الاستجابة الناجحة (200)
```json
{
  "success": true,
  "data": [
    {
      "id": 42,
      "patient_number": "PAT-2024-001",
      "name": "محمد أحمد",
      "email": "mohammad@email.com",
      "phone": "+966501234567",
      "date_of_birth": "1990-01-15",
      "gender": "male",
      "address": "الرياض، السعودية",
      "insurance_provider": "تكافل",
      "insurance_number": "INS-123456789",
      "medical_history": "ارتفاع ضغط الدم",
      "allergies": "البنسلين",
      "current_medication": "دواء ضغط الدم",
      "notes": "ملاحظات عامة",
      "recent_appointments": [
        {
          "id": 15,
          "appointment_at": "2024-06-05T10:30:00Z",
          "doctor": "د. فاطمة السعدي",
          "status": "completed"
        },
        {
          "id": 14,
          "appointment_at": "2024-05-20T14:00:00Z",
          "doctor": "د. علي محمد",
          "status": "cancelled"
        }
      ],
      "recent_invoices": [
        {
          "id": 89,
          "invoice_number": "INV-2024-001",
          "total": 1500.00,
          "status": "paid",
          "created_at": "2024-06-05"
        },
        {
          "id": 88,
          "invoice_number": "INV-2024-000",
          "total": 800.00,
          "status": "pending",
          "created_at": "2024-05-20"
        }
      ],
      "created_at": "2023-11-01T12:00:00Z"
    }
  ],
  "message": "Patients retrieved successfully"
}
```

---

## 2️⃣ الحصول على قائمة أسعار التأمين

### Endpoint
```
GET /api/clinic/insurance/companies/{id}/price-list-items
```

### الإذن المطلوب
```
Permissions: insurance.view|patients.view
```

### مثال الطلب
```bash
curl -X GET "https://api.dentaplus.local/api/clinic/insurance/companies/5/price-list-items?per_page=50" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

### الاستجابة الناجحة (200)
```json
{
  "success": true,
  "data": {
    "price_list": {
      "id": 8,
      "name": "قائمة أسعار تكافل 2024",
      "insurance_company_id": 5
    },
    "items": [
      {
        "id": 101,
        "code": "SRV-CLEAN",
        "service_name": "تنظيف الأسنان",
        "category": "عمليات وقائية",
        "unit_price": 150.00,
        "coverage_percentage": 80
      },
      {
        "id": 102,
        "code": "SRV-FILLING",
        "service_name": "حشوة بيضاء",
        "category": "علاجات",
        "unit_price": 300.00,
        "coverage_percentage": 50
      },
      {
        "id": 103,
        "code": "SRV-CROWN",
        "service_name": "تاج أسنان",
        "category": "تعويضات",
        "unit_price": 1200.00,
        "coverage_percentage": 40
      }
    ],
    "pagination": {
      "total": 45,
      "per_page": 50,
      "current_page": 1,
      "last_page": 1
    }
  },
  "message": "Price list items retrieved successfully"
}
```

---

## 3️⃣ إنشاء مطالبة مع عناصر (مع أسعار)

### Endpoint
```
POST /api/clinic/insurance/claims
```

### الإذن المطلوب
```
Permissions: insurance.create
```

### مثال الطلب - مع عناصر
```bash
curl -X POST "https://api.dentaplus.local/api/clinic/insurance/claims" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "insurance_company_id": 5,
    "patient_id": 42,
    "appointment_id": 15,
    "title": "مطالبة تأمين - تنظيف وحشوة",
    "description": "مطالبة مركبة لخدمات طب أسنان",
    "service_date": "2024-06-05",
    "coverage_percentage": 80,
    "patient_consent_required": true,
    "items": [
      {
        "insurance_price_list_item_id": 101,
        "service_name": "تنظيف الأسنان",
        "unit_price": 150.00,
        "quantity": 1,
        "notes": "تنظيف عام"
      },
      {
        "insurance_price_list_item_id": 102,
        "service_name": "حشوة بيضاء",
        "unit_price": 300.00,
        "quantity": 2,
        "notes": "حشويتان في الضروس"
      }
    ],
    "notes": "مطالبة عادية"
  }'
```

### مثال الطلب - بدون عناصر (نظام قديم)
```bash
curl -X POST "https://api.dentaplus.local/api/clinic/insurance/claims" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "insurance_company_id": 5,
    "patient_id": 42,
    "appointment_id": 15,
    "title": "مطالبة تأمين",
    "service_date": "2024-06-05",
    "coverage_percentage": 80,
    "gross_amount": 750.00,
    "notes": "مطالبة عادية"
  }'
```

### الاستجابة الناجحة (201)
```json
{
  "success": true,
  "data": {
    "id": 567,
    "claim_number": "CLM-20240608-ABCDEF",
    "clinic_id": 3,
    "status": "draft",
    "title": "مطالبة تأمين - تنظيف وحشوة",
    "description": "مطالبة مركبة لخدمات طب أسنان",
    "service_date": "2024-06-05",
    "coverage_percentage": 80.0,
    "gross_amount": 750.00,
    "patient_share_amount": 150.00,
    "insurance_share_amount": 600.00,
    "approved_amount": 0.0,
    "paid_amount": 0.0,
    "notes": "مطالبة عادية",
    "patient_consent_required": true,
    "patient_consent_uploaded_at": null,
    "consent_upload_required": true,
    "items": [
      {
        "id": 1001,
        "service_name": "تنظيف الأسنان",
        "code": null,
        "category_name": null,
        "unit_price": 150.0,
        "quantity": 1,
        "total_amount": 150.0,
        "notes": "تنظيف عام",
        "created_at": "2024-06-08T12:00:00Z"
      },
      {
        "id": 1002,
        "service_name": "حشوة بيضاء",
        "code": null,
        "category_name": null,
        "unit_price": 300.0,
        "quantity": 2,
        "total_amount": 600.0,
        "notes": "حشويتان في الضروس",
        "created_at": "2024-06-08T12:00:00Z"
      }
    ],
    "company": {
      "id": 5,
      "name": "تكافل",
      "code": "TAKAFOL"
    },
    "patient": {
      "id": 42,
      "patient_number": "PAT-2024-001",
      "name": "محمد أحمد"
    },
    "appointment": {
      "id": 15,
      "appointment_at": "2024-06-05T10:30:00Z"
    },
    "created_by": {
      "id": 8,
      "name": "د. علي محمد"
    },
    "created_at": "2024-06-08T12:00:00Z",
    "updated_at": "2024-06-08T12:00:00Z"
  },
  "message": "Insurance claim created successfully"
}
```

---

## 4️⃣ تحديث حالة المطالبة (مع إخطار WhatsApp)

### Endpoint
```
PATCH /api/clinic/insurance/claims/{id}
```

### الإذن المطلوب
```
Permissions: insurance.update
```

### مثال الطلب - الموافقة
```bash
curl -X PATCH "https://api.dentaplus.local/api/clinic/insurance/claims/567" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "approved",
    "approved_amount": 600.00,
    "status_notes": "تمت الموافقة على المطالبة"
  }'
```

**الإخطار المُرسل (WhatsApp):**
```
تمت الموافقة على مطالبتك رقم CLM-20240608-ABCDEF من شركة التأمين. يمكنك الدخول إلى بوابة المرضى: https://patients.dentaplus.local
```

### مثال الطلب - الموافقة الجزئية
```bash
curl -X PATCH "https://api.dentaplus.local/api/clinic/insurance/claims/567" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "partially_approved",
    "approved_amount": 450.00,
    "status_notes": "موافقة جزئية"
  }'
```

**الإخطار المُرسل (WhatsApp):**
```
تمت الموافقة الجزئية على مطالبتك رقم CLM-20240608-ABCDEF. المبلغ المعتمد: 450.00، المبلغ المستحق منك: 300.00. يمكنك الدخول إلى بوابة المرضى: https://patients.dentaplus.local
```

### مثال الطلب - الموافقة بحد أقصى
```bash
curl -X PATCH "https://api.dentaplus.local/api/clinic/insurance/claims/567" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "approved_with_limit",
    "approved_amount": 500.00,
    "status_notes": "موافقة بحد أقصى"
  }'
```

### مثال الطلب - الرفض
```bash
curl -X PATCH "https://api.dentaplus.local/api/clinic/insurance/claims/567" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "rejected",
    "status_notes": "لم تتطابق مع الشروط"
  }'
```

**الإخطار المُرسل (WhatsApp):**
```
نأسف، تم رفض مطالبتك رقم CLM-20240608-ABCDEF. برجاء التواصل مع العيادة
```

### الاستجابة الناجحة (200)
```json
{
  "success": true,
  "data": {
    "id": 567,
    "claim_number": "CLM-20240608-ABCDEF",
    "status": "approved",
    "approved_amount": 600.00,
    "patient_share_amount": 150.00,
    "insurance_share_amount": 600.00,
    "status_notes": "تمت الموافقة على المطالبة",
    "reviewed_at": "2024-06-08T12:15:00Z",
    ...
  },
  "message": "Insurance claim updated successfully"
}
```

**ملاحظة:** إذا فشل إرسال WhatsApp، فإن المطالبة تُحفظ بنجاح (non-blocking).

---

## 5️⃣ رفع وثيقة موافقة المريض

### Endpoint
```
POST /api/clinic/insurance/claims/{id}/patient-consent
```

### الإذن المطلوب
```
Permissions: insurance.update
```

### مثال الطلب
```bash
curl -X POST "https://api.dentaplus.local/api/clinic/insurance/claims/567/patient-consent" \
  -H "Authorization: Bearer {token}" \
  -F "file=@/path/to/consent.pdf" \
  -F "title=استمارة الموافقة" \
  -F "notes=موافقة المريض على العلاج"
```

### الاستجابة الناجحة (201)
```json
{
  "success": true,
  "data": {
    "claim": {
      "id": 567,
      "claim_number": "CLM-20240608-ABCDEF",
      "status": "draft",
      "patient_consent_required": true,
      "patient_consent_uploaded_at": "2024-06-08T12:20:00Z",
      "consent_upload_required": false,
      "patient_consent": {
        "id": 2001,
        "document_type": "insurance_consent",
        "title": "استمارة الموافقة",
        "original_name": "consent.pdf",
        "mime_type": "application/pdf",
        "size": 245620,
        "notes": "موافقة المريض على العلاج",
        "created_at": "2024-06-08T12:20:00Z"
      },
      ...
    },
    "document": {
      "id": 2001,
      "document_type": "insurance_consent",
      "title": "استمارة الموافقة",
      "original_name": "consent.pdf",
      "mime_type": "application/pdf",
      "size": 245620,
      "notes": "موافقة المريض على العلاج",
      "created_at": "2024-06-08T12:20:00Z"
    }
  },
  "message": "Patient consent document uploaded successfully"
}
```

---

## ⚠️ رسائل الخطأ الشائعة

### 403 - Unauthorized
```json
{
  "success": false,
  "message": "Clinic account is not linked to a clinic.",
  "code": 403
}
```

### 404 - Not Found
```json
{
  "success": false,
  "message": "Insurance claim not found.",
  "code": 404
}
```

### 422 - Validation Error
```json
{
  "success": false,
  "message": "Validation failed",
  "code": 422,
  "errors": {
    "patient_id": ["Patient not found for this clinic."],
    "items.0.insurance_price_list_item_id": ["Price list item does not belong to selected insurance company."]
  }
}
```

### 422 - Status Transition Error
```json
{
  "success": false,
  "message": "Insurance claim status transition is not allowed.",
  "code": 422,
  "errors": {
    "status": ["The requested status transition is not allowed."]
  }
}
```

---

## 🔄 Workflow مثالي

```
1. إنشاء مطالبة (POST /claims)
   ↓
2. إذا كانت patient_consent_required=true:
   رفع الوثيقة (POST /claims/{id}/patient-consent)
   ↓
3. تحديث حالة المطالبة (PATCH /claims/{id})
   - Status: submitted/approved/partially_approved/approved_with_limit/rejected
   - (WhatsApp notification sent automatically)
   ↓
4. إذا تمت الموافقة: تحديث الحالة إلى paid
```

---

## 📱 WhatsApp Status الممكنة

| الحالة | الرسالة | الإخطار |
|------|--------|--------|
| `approved` | الموافقة | ✅ نعم |
| `rejected` | الرفض | ✅ نعم |
| `partially_approved` | الموافقة الجزئية | ✅ نعم |
| `approved_with_limit` | الموافقة بحد أقصى | ✅ نعم |
| `submitted` | التقديم | ❌ لا |
| `draft` | مسودة | ❌ لا |
| `paid` | الدفع | ❌ لا |
| `cancelled` | الإلغاء | ❌ لا |

---

**✅ جميع الـ endpoints جاهزة للاستخدام!**

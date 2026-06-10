# DentaPlus Insurance Claims System - Implementation Report

**التاريخ:** June 8, 2026  
**الحالة:** ✅ تم التنفيذ بنجاح  
**عدد الملفات الجديدة:** 9  
**عدد الملفات المعدلة:** 10  

---

## 📋 ملخص التعديلات

تم تنفيذ **4 تحسينات رئيسية** للنظام بطريقة آمنة وتوافقية:

### 1️⃣ **تحسين البحث عن المرضى (Patient Lookup)**
### 2️⃣ **ربط أسعار التأمين بـ Claims (Insurance Pricing Integration)**
### 3️⃣ **إخطارات WhatsApp عند تغيير الحالة (WhatsApp Status Notifications)**
### 4️⃣ **رفع وثائق الموافقة من المريض (Patient Consent Documents)**

---

## 🆕 الملفات الجديدة المُنشأة

### **1. Models (2 ملف)**

#### [app/Models/Clinic/Insurance/InsuranceClaimItem.php](app/Models/Clinic/Insurance/InsuranceClaimItem.php)
- **الوصف:** نموذج لعناصر المطالبة بالتأمين
- **الحقول:** id, insurance_claim_id FK, insurance_price_list_item_id, service_id, code, service_name, category_id, category_name, unit_price, quantity, total_amount, notes, timestamps
- **العلاقات:** belongsTo InsuranceClaim, belongsTo InsurancePriceListItem, belongsTo Service
- **الاستخدام:** تخزين بيانات الخدمات المضمنة في كل مطالبة

#### [app/Models/PatientDocument.php](app/Models/PatientDocument.php)
- **الوصف:** نموذج لوثائق المرضى (الموافقات والمستندات)
- **الحقول:** id, clinic_id FK, patient_id FK, uploaded_by FK, document_type, title, file_path, original_name, mime_type, size, related_type, related_id, notes, timestamps, softDeletes
- **العلاقات:** belongsTo Clinic, belongsTo Patient, belongsTo User (uploader)
- **الاستخدام:** تخزين وثائق الموافقة والمستندات المتعلقة بالمرضى

---

### **2. Services (2 ملف)**

#### [app/Services/Clinic/Insurance/ClaimStatusWhatsAppNotificationService.php](app/Services/Clinic/Insurance/ClaimStatusWhatsAppNotificationService.php)
- **الوصف:** خدمة إرسال إخطارات WhatsApp عند تغيير حالة المطالبة
- **الحالات المدعومة:**
  - ✅ `STATUS_APPROVED`: "تمت الموافقة على مطالبتك..."
  - ❌ `STATUS_REJECTED`: "نأسف، تم رفض مطالبتك..."
  - ⚠️ `STATUS_PARTIALLY_APPROVED`: يتضمن حساب المبلغ المستحق من المريض
  - ⚠️ `STATUS_APPROVED_WITH_LIMIT`: يتضمن حساب المبلغ المستحق من المريض
- **الميزات:**
  - الرسائل بالعربية فقط
  - عدم كشف بيانات حساسة (لا كلمات المرور، لا بيانات شخصية)
  - غير حجزية (non-blocking) - فشل الإرسال لن يوقف تحديث المطالبة
  - تسجيل محاولات الإرسال في جدول WhatsAppMessage

#### [app/Services/Clinic/Insurance/PatientLookupService.php](app/Services/Clinic/Insurance/PatientLookupService.php)
- **الوصف:** خدمة البحث عن المرضى برموز مختلفة
- **المعاملات:**
  - `clinicId` (required): معرّف العيادة لـ Clinic Isolation
  - `patientId` (optional): البحث بـ ID المريض
  - `patientNumber` (optional): البحث برقم المريض
  - `query` (optional): بحث نصي في (رقم المريض, اسم, هاتف, رقم التأمين)
  - `limit` (optional): حد أقصى للنتائج (افتراضي: 10)
- **البيانات المرجعة:** معلومات المريض الكاملة + آخر 5 مواعيد + آخر 5 فواتير

---

### **3. Requests (2 ملف)**

#### [app/Http/Requests/Clinic/Insurance/PatientLookupRequest.php](app/Http/Requests/Clinic/Insurance/PatientLookupRequest.php)
- **القواعد:** التحقق من معاملات البحث (patient_id, patient_number, query, limit)

#### [app/Http/Requests/Clinic/Insurance/UploadPatientConsentRequest.php](app/Http/Requests/Clinic/Insurance/UploadPatientConsentRequest.php)
- **القواعد:**
  - `file` (required): ملف PDF/صورة، حد أقصى 10 MB
  - `title` (optional): عنوان الوثيقة
  - `notes` (optional): ملاحظات

---

### **4. Resources (2 ملف)**

#### [app/Http/Resources/Clinic/Insurance/InsuranceClaimItemResource.php](app/Http/Resources/Clinic/Insurance/InsuranceClaimItemResource.php)
- **الحقول المُرجعة:** id, service_name, code, category_name, unit_price, quantity, total_amount, notes, created_at

#### [app/Http/Resources/PatientDocumentResource.php](app/Http/Resources/PatientDocumentResource.php)
- **الحقول المُرجعة:** id, document_type, title, original_name, mime_type, size, notes, created_at

---

### **5. Migrations (3 ملفات)**

#### [database/migrations/2026_06_08_100000_create_insurance_claim_items_table.php](database/migrations/2026_06_08_100000_create_insurance_claim_items_table.php)
```sql
CREATE TABLE insurance_claim_items (
  id BIGINT PRIMARY KEY,
  insurance_claim_id FK → insurance_claims,
  insurance_price_list_item_id FK → insurance_price_list_items,
  service_id FK → services,
  code VARCHAR(255),
  service_name VARCHAR(255),
  category_id BIGINT,
  category_name VARCHAR(255),
  unit_price DECIMAL(12,2),
  quantity INTEGER DEFAULT 1,
  total_amount DECIMAL(12,2),
  notes TEXT,
  timestamps
)
```

#### [database/migrations/2026_06_08_100100_create_patient_documents_table.php](database/migrations/2026_06_08_100100_create_patient_documents_table.php)
```sql
CREATE TABLE patient_documents (
  id BIGINT PRIMARY KEY,
  clinic_id FK → clinics,
  patient_id FK → patients,
  uploaded_by FK → users,
  document_type VARCHAR(255),
  title VARCHAR(255),
  file_path VARCHAR(255),
  original_name VARCHAR(255),
  mime_type VARCHAR(255),
  size INTEGER,
  related_type VARCHAR(255),
  related_id BIGINT,
  notes TEXT,
  timestamps,
  softDeletes
)
```

#### [database/migrations/2026_06_08_100200_add_consent_fields_to_insurance_claims_table.php](database/migrations/2026_06_08_100200_add_consent_fields_to_insurance_claims_table.php)
- **الأعمدة المضافة إلى insurance_claims:**
  - `patient_consent_required` BOOLEAN DEFAULT FALSE
  - `patient_consent_document_id` FK → patient_documents
  - `patient_consent_uploaded_at` TIMESTAMP nullable

---

## 📝 الملفات المعدلة

### **1. Models**

#### [app/Models/Clinic/Insurance/InsuranceClaim.php](app/Models/Clinic/Insurance/InsuranceClaim.php)
- ✅ إضافة `STATUS_APPROVED_WITH_LIMIT = 'approved_with_limit'`
- ✅ تحديث `statuses()` method ليشمل الحالة الجديدة
- ✅ إضافة أعمدة جديدة إلى `$fillable`:
  - `patient_consent_required`
  - `patient_consent_document_id`
  - `patient_consent_uploaded_at`
- ✅ إضافة علاقات جديدة:
  - `items()` hasMany InsuranceClaimItem
  - `patientConsent()` belongsTo PatientDocument

#### [app/Models/Patient.php](app/Models/Patient.php)
- ✅ إضافة `documents()` hasMany PatientDocument

---

### **2. Services**

#### [app/Services/Clinic/Insurance/InsuranceClaimService.php](app/Services/Clinic/Insurance/InsuranceClaimService.php)
- ✅ تحديث `store()` method:
  - دعم `items` array في البيانات المدخلة
  - حساب `gross_amount` تلقائياً من items إذا كانت موجودة
  - دعم `patient_consent_required` flag
  - إنشاء items تلقائياً بعد إنشاء المطالبة

- ✅ تحديث `update()` method:
  - دعم تحديث items
  - إعادة حساب `gross_amount` من items
  - إطلاق إخطارات WhatsApp عند تغيير الحالة (non-blocking)

- ✅ إضافة methods جديدة:
  - `createItems()`: إنشاء عناصر المطالبة
  - `calculateGrossAmountFromItems()`: حساب الإجمالي من العناصر
  - `triggerStatusNotification()`: إرسال إخطارات WhatsApp (non-blocking)
  - `validateClaimItems()`: التحقق من items (نفس العيادة، نفس شركة التأمين)

- ✅ تحديث `isValidTransition()`:
  - إضافة `STATUS_APPROVED_WITH_LIMIT` إلى انتقالات الحالة الصالحة

---

### **3. Repositories**

#### [app/Repositories/Clinic/Insurance/InsuranceClaimRepository.php](app/Repositories/Clinic/Insurance/InsuranceClaimRepository.php)
- ✅ تحديث `listForClinic()`: إضافة eager loading للـ `items` و `patientConsent`
- ✅ تحديث `findForClinic()`: إضافة eager loading للـ `items` و `patientConsent`
- ✅ تحديث `update()`: إضافة eager loading للـ `items` و `patientConsent`

---

### **4. Controllers**

#### [app/Http/Controllers/Api/Clinic/Insurance/InsuranceClaimController.php](app/Http/Controllers/Api/Clinic/Insurance/InsuranceClaimController.php)
- ✅ إضافة `patientLookup()` action:
  - معاملات: patient_id, patient_number, query, limit
  - التحقق من Clinic Isolation
  - إرجاع بيانات المرضى الكاملة مع المواعيد والفواتير

- ✅ إضافة `uploadConsent()` action:
  - التحقق من وجود الملف
  - حفظ الملف في `storage/public/patient-documents/{patient_id}`
  - إنشاء record في PatientDocument
  - ربط الملف بـ claim
  - تحديث timestamps
  - إرجاع claim resource و document resource

#### [app/Http/Controllers/Api/Clinic/Insurance/InsuranceCompanyController.php](app/Http/Controllers/Api/Clinic/Insurance/InsuranceCompanyController.php)
- ✅ إضافة `priceListItems()` action:
  - معامل: id (insurance_company_id)
  - إرجاع price list items مع pagination
  - معلومات price list الأساسية

---

### **5. Requests**

#### [app/Http/Requests/Clinic/Insurance/StoreInsuranceClaimRequest.php](app/Http/Requests/Clinic/Insurance/StoreInsuranceClaimRequest.php)
- ✅ تحديث قواعد التحقق:
  - جعل `gross_amount` nullable (لأنه يحسب من items)
  - إضافة `patient_consent_required` boolean
  - إضافة `items` array validation:
    - `items.*.insurance_price_list_item_id` nullable
    - `items.*.service_id` nullable
    - `items.*.quantity` min:1
    - `items.*.unit_price` min:0
    - `items.*.service_name` required_with:items
    - `items.*.notes` nullable

#### [app/Http/Requests/Clinic/Insurance/UpdateInsuranceClaimRequest.php](app/Http/Requests/Clinic/Insurance/UpdateInsuranceClaimRequest.php)
- ✅ نفس التحديثات مع `sometimes` بدلاً من `required`

---

### **6. Resources**

#### [app/Http/Resources/Clinic/Insurance/InsuranceClaimResource.php](app/Http/Resources/Clinic/Insurance/InsuranceClaimResource.php)
- ✅ إضافة حقول جديدة (backward compatible):
  - `patient_consent_required`: boolean
  - `patient_consent_uploaded_at`: timestamp
  - `consent_upload_required`: flag for frontend (true إذا كان consent_required=true وليس هناك document)
  - `items`: collection of InsuranceClaimItemResource
  - `patient_consent`: PatientDocumentResource (whenLoaded)

---

### **7. Routes**

#### [routes/api/clinic.php](routes/api/clinic.php)
- ✅ إضافة **3 routes جديدة** تحت Insurance prefix:

```php
// Patient Lookup
GET /api/clinic/insurance/patients/lookup
  Permissions: insurance.view|patients.view

// Price List Items
GET /api/clinic/insurance/companies/{id}/price-list-items
  Permissions: insurance.view|patients.view

// Upload Patient Consent
POST /api/clinic/insurance/claims/{id}/patient-consent
  Permissions: insurance.update
```

---

## 🔐 الميزات الأمنية المُطبقة

### **Clinic Isolation (عزل البيانات حسب العيادة)**
- ✅ كل query يتحقق من `clinic_id`
- ✅ عدم إرجاع بيانات من عيادات أخرى
- ✅ validation في items يتأكد من نفس العيادة والتأمين

### **Permission-based Access Control**
- ✅ `insurance.view`: عرض المطالبات والشركات
- ✅ `insurance.create`: إنشاء مطالبات جديدة
- ✅ `insurance.update`: تحديث المطالبات
- ✅ `insurance.delete`: حذف المطالبات
- ✅ `patients.view`: بحث وعرض المرضى

### **Data Validation**
- ✅ التحقق من وجود جميع الـ ForeignKeys
- ✅ التحقق من مطابقة المريض مع الفاتورة والموعد
- ✅ التحقق من items من نفس شركة التأمين
- ✅ التحقق من رفع الملفات (نوع، حجم)

### **Non-blocking WhatsApp Notifications**
- ✅ فشل الإخطار لن يوقف تحديث المطالبة
- ✅ تسجيل كل المحاولات (نجاح/فشل)
- ✅ بدون كشف بيانات حساسة

---

## 🔄 التوافقية العكسية (Backward Compatibility)

### **✅ جميع التغييرات متوافقة مع النظام القديم**

- ✅ **Store/Update بدون items**: يعمل بنفس الطريقة القديمة
- ✅ **Gross amount افتراضي**: إذا لم يتم توفير items، استخدم gross_amount المدخل
- ✅ **جميع response fields محفوظة**: حقول جديدة فقط مع `whenLoaded()`
- ✅ **Consent اختيارية**: flag اختياري في الـ create/update
- ✅ **Items اختيارية**: يمكن عدم إرسالها في الـ requests

---

## 📊 ملخص تقني

| النوع | العدد | الملفات |
|-------|-------|--------|
| **Models جديدة** | 2 | InsuranceClaimItem, PatientDocument |
| **Services جديدة** | 2 | ClaimStatusWhatsAppNotificationService, PatientLookupService |
| **Requests جديدة** | 2 | PatientLookupRequest, UploadPatientConsentRequest |
| **Resources جديدة** | 2 | InsuranceClaimItemResource, PatientDocumentResource |
| **Migrations جديدة** | 3 | insurance_claim_items, patient_documents, consent fields |
| **Controllers معدلة** | 2 | InsuranceClaimController (+2), InsuranceCompanyController (+1) |
| **Requests معدلة** | 2 | StoreInsuranceClaimRequest, UpdateInsuranceClaimRequest |
| **Resources معدلة** | 1 | InsuranceClaimResource |
| **Services معدلة** | 1 | InsuranceClaimService |
| **Repositories معدلة** | 1 | InsuranceClaimRepository |
| **Models معدلة** | 2 | InsuranceClaim, Patient |
| **Routes معدلة** | 1 | clinic.php (+3 routes) |
| **المجموع** | **21** | 9 جديدة + 10 معدلة + 3 migrations |

---

## 🚀 الخطوات التالية (اختيارية)

### **للبدء في الاستخدام:**

```bash
# 1. تطبيق الـ migrations
php artisan migrate

# 2. (اختياري) تشغيل الـ seeders
php artisan db:seed

# 3. اختبار الـ endpoints الجديدة
POST /api/clinic/insurance/patients/lookup
GET /api/clinic/insurance/companies/{id}/price-list-items
POST /api/clinic/insurance/claims/{id}/patient-consent
```

### **للاختبار:**

```bash
# تشغيل الـ tests
php artisan test

# اختبار معين
php artisan test tests/Feature/Insurance/InsuranceClaimTest.php
```

---

## 📞 ملاحظات مهمة

### **WhatsApp Configuration:**
```php
// في config/services.php أو .env
WHATSAPP_PROVIDER=meta|twilio
WHATSAPP_API_KEY=...
PATIENT_PORTAL_URL=https://patients.dentaplus.local (optional)
```

### **File Upload Configuration:**
```php
// في config/filesystems.php
'public' disk يجب أن يكون قابل للوصول من الـ web
```

### **Database Constraints:**
- ✅ clinic_id isolation في كل المستندات
- ✅ softDeletes للوثائق (حذف ناعم)
- ✅ cascadeOnDelete للعلاقات المهمة

---

**✅ تم إكمال التنفيذ بنجاح!**

جميع الميزات الأربعة تم تنفيذها بطريقة آمنة وموثوقة مع الحفاظ على التوافقية العكسية الكاملة.

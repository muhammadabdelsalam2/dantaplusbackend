# Database Schema Changes - Documentation

## 📊 الجداول الجديدة والأعمدة المضافة

---

## 1. جدول `insurance_claim_items` (جديد)

### الغرض
تخزين عناصر (خدمات) كل مطالبة تأمين بشكل منفصل لسهولة الإدارة والتقارير.

### الهيكل
```sql
CREATE TABLE insurance_claim_items (
  -- Primary Key
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  
  -- Foreign Keys
  insurance_claim_id BIGINT NOT NULL,
  CONSTRAINT fk_insurance_claim_items_claim 
    FOREIGN KEY (insurance_claim_id) REFERENCES insurance_claims(id) 
    ON DELETE CASCADE,
  
  insurance_price_list_item_id BIGINT NULLABLE,
  CONSTRAINT fk_insurance_claim_items_price_list 
    FOREIGN KEY (insurance_price_list_item_id) REFERENCES insurance_price_list_items(id) 
    ON DELETE SET NULL,
  
  service_id BIGINT NULLABLE,
  CONSTRAINT fk_insurance_claim_items_service 
    FOREIGN KEY (service_id) REFERENCES services(id) 
    ON DELETE SET NULL,
  
  -- Data Columns
  code VARCHAR(255) NULLABLE,
  service_name VARCHAR(255) NOT NULL,
  category_id BIGINT NULLABLE,
  category_name VARCHAR(255) NULLABLE,
  unit_price DECIMAL(12, 2) NOT NULL,
  quantity INTEGER NOT NULL DEFAULT 1,
  total_amount DECIMAL(12, 2) NOT NULL,
  notes TEXT NULLABLE,
  
  -- Timestamps
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Indexes
  INDEX idx_insurance_claim_id (insurance_claim_id),
  INDEX idx_insurance_price_list_item_id (insurance_price_list_item_id),
  INDEX idx_service_id (service_id)
);
```

### الحقول الهامة
- `total_amount`: يُحسب كـ `unit_price * quantity` (يُخزن مباشرة)
- `category_*`: نسخ من بيانات قائمة الأسعار (للحفاظ على البيانات التاريخية)
- `notes`: ملاحظات خاصة بهذا العنصر

---

## 2. جدول `patient_documents` (جديد)

### الغرض
تخزين جميع وثائق المرضى (الموافقات، التقارير، إلخ) مع ربطها بـ سجلات أخرى.

### الهيكل
```sql
CREATE TABLE patient_documents (
  -- Primary Key
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  
  -- Foreign Keys (Clinic Isolation)
  clinic_id BIGINT NOT NULL,
  CONSTRAINT fk_patient_documents_clinic 
    FOREIGN KEY (clinic_id) REFERENCES clinics(id) 
    ON DELETE CASCADE,
  
  patient_id BIGINT NOT NULL,
  CONSTRAINT fk_patient_documents_patient 
    FOREIGN KEY (patient_id) REFERENCES patients(id) 
    ON DELETE CASCADE,
  
  uploaded_by BIGINT NULLABLE,
  CONSTRAINT fk_patient_documents_uploaded_by 
    FOREIGN KEY (uploaded_by) REFERENCES users(id) 
    ON DELETE SET NULL,
  
  -- Document Data
  document_type VARCHAR(255) NOT NULL,
  title VARCHAR(255) NULLABLE,
  file_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NULLABLE,
  mime_type VARCHAR(255) NULLABLE,
  size INTEGER NULLABLE,
  
  -- Relationships (Polymorphic)
  related_type VARCHAR(255) NULLABLE,
  related_id BIGINT NULLABLE,
  
  notes TEXT NULLABLE,
  
  -- Timestamps
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULLABLE,
  
  -- Indexes
  INDEX idx_clinic_patient (clinic_id, patient_id),
  INDEX idx_document_type (document_type),
  INDEX idx_related (related_type, related_id),
  INDEX idx_deleted_at (deleted_at)
);
```

### الحقول الهامة
- `document_type`: أنواع الوثائق (insurance_consent, medical_report, prescription, etc)
- `related_type/related_id`: معرّفات polymorphic للربط مع أي جدول
  - مثال: related_type='insurance_claim', related_id=567
- `clinic_id`: isolation - كل عيادة ترى وثائقها فقط
- `deleted_at`: soft delete للحفاظ على البيانات التاريخية

---

## 3. إضافة أعمدة إلى جدول `insurance_claims` (موجود)

### الأعمدة المضافة
```sql
ALTER TABLE insurance_claims ADD COLUMN
  patient_consent_required BOOLEAN DEFAULT FALSE
  AFTER status;

ALTER TABLE insurance_claims ADD COLUMN
  patient_consent_document_id BIGINT NULLABLE
  AFTER patient_consent_required,
  ADD CONSTRAINT fk_insurance_claims_consent 
    FOREIGN KEY (patient_consent_document_id) REFERENCES patient_documents(id) 
    ON DELETE SET NULL;

ALTER TABLE insurance_claims ADD COLUMN
  patient_consent_uploaded_at TIMESTAMP NULLABLE
  AFTER patient_consent_document_id;
```

### شرح الأعمدة

| الحقل | النوع | الوصف |
|------|--------|--------|
| `patient_consent_required` | BOOLEAN | هل تتطلب المطالبة موافقة كتابية من المريض |
| `patient_consent_document_id` | FK | معرّف الوثيقة المرفوعة |
| `patient_consent_uploaded_at` | TIMESTAMP | وقت رفع الوثيقة |

---

## 🔄 Data Flow

### عند إنشاء مطالبة مع عناصر:
```
POST /api/clinic/insurance/claims
{
  items: [
    { service_name: "تنظيف", unit_price: 150, quantity: 1 },
    { service_name: "حشوة", unit_price: 300, quantity: 2 }
  ]
}

↓ (في Database)

insurance_claims (1):
  - id: 567
  - gross_amount: 750 (محسوب من items: 150 + 600)
  
insurance_claim_items (2):
  - id: 1001, service_name: تنظيف, total_amount: 150
  - id: 1002, service_name: حشوة, total_amount: 600
```

### عند رفع وثيقة موافقة:
```
POST /api/clinic/insurance/claims/567/patient-consent
{ file: consent.pdf }

↓ (في Database)

patient_documents (1):
  - id: 2001
  - clinic_id: 3
  - patient_id: 42
  - document_type: insurance_consent
  - file_path: patient-documents/42/abcdef.pdf
  - related_type: insurance_claim
  - related_id: 567

insurance_claims (update):
  - patient_consent_document_id: 2001
  - patient_consent_uploaded_at: 2024-06-08 12:20:00
```

---

## 🔐 Foreign Key Constraints

### Cascade Delete
- `insurance_claim_items` ← `insurance_claims` (CASCADE)
  عند حذف مطالبة، حذف جميع عناصرها تلقائياً

- `patient_documents` ← `clinics` (CASCADE)
  عند حذف عيادة، حذف جميع وثائقها

- `patient_documents` ← `patients` (CASCADE)
  عند حذف مريض، حذف جميع وثائقه

### Set Null
- `insurance_claim_items.insurance_price_list_item_id` ← `insurance_price_list_items` (SET NULL)
- `insurance_claim_items.service_id` ← `services` (SET NULL)
- `patient_documents.uploaded_by` ← `users` (SET NULL)
- `insurance_claims.patient_consent_document_id` ← `patient_documents` (SET NULL)

**السبب:** الحفاظ على البيانات التاريخية للمطالبات والوثائق حتى لو حُذفت السجلات الأخرى.

---

## 📈 Indexes للأداء

### على `insurance_claim_items`
```sql
INDEX idx_insurance_claim_id (insurance_claim_id)
  -- للبحث السريع عن عناصر مطالبة معينة

INDEX idx_insurance_price_list_item_id (insurance_price_list_item_id)
  -- للتحقق من سعر العنصر

INDEX idx_service_id (service_id)
  -- للربط مع الخدمات
```

### على `patient_documents`
```sql
INDEX idx_clinic_patient (clinic_id, patient_id)
  -- للبحث السريع عن وثائق مريض معين في عيادة معينة

INDEX idx_document_type (document_type)
  -- تصفية الوثائق حسب النوع

INDEX idx_related (related_type, related_id)
  -- البحث السريع عن الوثائق المرتبطة بـ claims/invoices/etc

INDEX idx_deleted_at (deleted_at)
  -- تسريع الـ soft delete queries
```

---

## 🔒 Clinic Isolation

**جميع الاستعلامات تتحقق من `clinic_id`:**

```php
// مثال في Code
$items = InsuranceClaimItem::whereHas('claim', function ($q) {
    $q->where('clinic_id', auth()->user()->clinic_id);
})->get();

// أو أفضل - عبر Scope
$items = InsuranceClaim::forClinic($clinicId)->with('items')->get();
```

**لا يمكن** أي مستخدم رؤية بيانات عيادة أخرى.

---

## 💾 Data Retention Policy

### Soft Delete
- `patient_documents.deleted_at`: يُحفظ في قاعدة البيانات عند الحذف
- في الـ queries الافتراضية، الوثائق المحذوفة **لا تظهر**
- يمكن استرجاعها: `PatientDocument::withTrashed()->find($id)`

### Hard Delete (نادراً)
```php
// لا تستخدم إلا في حالات قانونية (مثل GDPR)
$document->forceDelete();
```

---

## 🔄 Migration Execution Order

### الترتيب الصحيح:
```
1. 2026_06_08_100000_create_insurance_claim_items_table.php
   (inserts ForeignKey → insurance_claims جديدة)

2. 2026_06_08_100100_create_patient_documents_table.php
   (جدول مستقل)

3. 2026_06_08_100200_add_consent_fields_to_insurance_claims_table.php
   (يضيف FK → patient_documents)
```

**الأمان:** كل migration تتحقق من وجود الجدول قبل الإضافة.

---

## ✅ Validation Rules

### عند إنشاء `insurance_claim_items`:
- ✅ `insurance_claim_id` يجب أن يكون FK موجود
- ✅ `insurance_price_list_item_id` (إذا موجود) يجب أن يكون من نفس شركة التأمين
- ✅ `service_name` مطلوب وغير فارغ
- ✅ `unit_price` > 0
- ✅ `quantity` >= 1

### عند رفع `patient_document`:
- ✅ `file` مطلوب ونوع صحيح (pdf, jpg, png, doc, docx)
- ✅ حجم < 10 MB
- ✅ `clinic_id` من المستخدم الحالي
- ✅ `patient_id` يجب أن يكون من نفس العيادة

---

## 📊 Sample Queries

### عرض جميع عناصر مطالبة معينة:
```php
$claim = InsuranceClaim::with('items')->find(567);
foreach ($claim->items as $item) {
    echo $item->service_name . " - " . $item->total_amount;
}
```

### البحث عن وثائق مريض معين:
```php
$documents = PatientDocument::where('patient_id', 42)
    ->where('clinic_id', 3)
    ->where('document_type', 'insurance_consent')
    ->get();
```

### حساب إجمالي عناصر مطالبة:
```php
$total = InsuranceClaimItem::where('insurance_claim_id', 567)
    ->sum('total_amount');
```

### تقرير عن الموافقات المنتظرة:
```php
$pendingConsents = InsuranceClaim::where('patient_consent_required', true)
    ->where('patient_consent_document_id', null)
    ->where('clinic_id', 3)
    ->count();
```

---

**✅ جميع الجداول والأعمدة جاهزة للاستخدام!**

يمكنك الآن تشغيل:
```bash
php artisan migrate
```

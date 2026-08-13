<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | app.locale is 'id' (see .env), but Laravel only ships an 'en' language
    | directory out of the box — without this file every API validation
    | error falls back to raw English ("The selected category is invalid.")
    | no matter what the controller intends. This makes Indonesian the
    | default for every validate() call across the app, present and future,
    | instead of requiring each controller to spell out its own messages.
    |
    */

    'accepted' => ':attribute wajib disetujui.',
    'accepted_if' => ':attribute wajib disetujui ketika :other bernilai :value.',
    'active_url' => ':attribute bukan URL yang valid.',
    'after' => ':attribute harus tanggal setelah :date.',
    'after_or_equal' => ':attribute harus tanggal setelah atau sama dengan :date.',
    'alpha' => ':attribute hanya boleh berisi huruf.',
    'alpha_dash' => ':attribute hanya boleh berisi huruf, angka, strip, dan garis bawah.',
    'alpha_num' => ':attribute hanya boleh berisi huruf dan angka.',
    'any_of' => ':attribute tidak valid.',
    'array' => ':attribute harus berupa array.',
    'ascii' => ':attribute hanya boleh berisi karakter dan simbol satu byte.',
    'before' => ':attribute harus tanggal sebelum :date.',
    'before_or_equal' => ':attribute harus tanggal sebelum atau sama dengan :date.',
    'between' => [
        'array' => ':attribute harus memiliki antara :min sampai :max item.',
        'file' => ':attribute harus berukuran antara :min sampai :max kilobyte.',
        'numeric' => ':attribute harus bernilai antara :min sampai :max.',
        'string' => ':attribute harus berisi antara :min sampai :max karakter.',
    ],
    'boolean' => ':attribute harus bernilai benar atau salah.',
    'can' => ':attribute berisi nilai yang tidak diizinkan.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'contains' => ':attribute belum berisi nilai yang wajib ada.',
    'current_password' => 'Kata sandi salah.',
    'date' => ':attribute bukan tanggal yang valid.',
    'date_equals' => ':attribute harus tanggal yang sama dengan :date.',
    'date_format' => 'Format :attribute tidak sesuai (:format).',
    'decimal' => ':attribute harus memiliki :decimal angka desimal.',
    'declined' => ':attribute wajib ditolak.',
    'declined_if' => ':attribute wajib ditolak ketika :other bernilai :value.',
    'different' => ':attribute dan :other harus berbeda.',
    'digits' => ':attribute harus terdiri dari :digits digit.',
    'digits_between' => ':attribute harus terdiri dari :min sampai :max digit.',
    'dimensions' => 'Dimensi gambar :attribute tidak valid.',
    'distinct' => ':attribute memiliki nilai yang duplikat.',
    'doesnt_contain' => ':attribute tidak boleh berisi salah satu dari: :values.',
    'doesnt_end_with' => ':attribute tidak boleh diakhiri dengan salah satu dari: :values.',
    'doesnt_start_with' => ':attribute tidak boleh diawali dengan salah satu dari: :values.',
    'email' => 'Format :attribute tidak valid.',
    'encoding' => ':attribute harus menggunakan enkode :encoding.',
    'ends_with' => ':attribute harus diakhiri dengan salah satu dari: :values.',
    'enum' => ':attribute yang dipilih tidak valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'extensions' => ':attribute harus memiliki salah satu ekstensi berikut: :values.',
    'file' => ':attribute harus berupa berkas.',
    'filled' => ':attribute wajib diisi.',
    'gt' => [
        'array' => ':attribute harus memiliki lebih dari :value item.',
        'file' => ':attribute harus lebih besar dari :value kilobyte.',
        'numeric' => ':attribute harus lebih besar dari :value.',
        'string' => ':attribute harus lebih dari :value karakter.',
    ],
    'gte' => [
        'array' => ':attribute harus memiliki :value item atau lebih.',
        'file' => ':attribute harus lebih besar atau sama dengan :value kilobyte.',
        'numeric' => ':attribute harus lebih besar atau sama dengan :value.',
        'string' => ':attribute harus lebih dari atau sama dengan :value karakter.',
    ],
    'hex_color' => ':attribute harus berupa warna heksadesimal yang valid.',
    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'in_array' => ':attribute harus ada di dalam :other.',
    'in_array_keys' => ':attribute harus berisi setidaknya salah satu dari: :values.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'ip' => ':attribute harus berupa alamat IP yang valid.',
    'ipv4' => ':attribute harus berupa alamat IPv4 yang valid.',
    'ipv6' => ':attribute harus berupa alamat IPv6 yang valid.',
    'json' => ':attribute harus berupa string JSON yang valid.',
    'list' => ':attribute harus berupa daftar (list).',
    'lowercase' => ':attribute harus berupa huruf kecil.',
    'lt' => [
        'array' => ':attribute harus memiliki kurang dari :value item.',
        'file' => ':attribute harus kurang dari :value kilobyte.',
        'numeric' => ':attribute harus kurang dari :value.',
        'string' => ':attribute harus kurang dari :value karakter.',
    ],
    'lte' => [
        'array' => ':attribute tidak boleh lebih dari :value item.',
        'file' => ':attribute harus kurang dari atau sama dengan :value kilobyte.',
        'numeric' => ':attribute harus kurang dari atau sama dengan :value.',
        'string' => ':attribute harus kurang dari atau sama dengan :value karakter.',
    ],
    'mac_address' => ':attribute harus berupa alamat MAC yang valid.',
    'max' => [
        'array' => ':attribute tidak boleh lebih dari :max item.',
        'file' => 'Ukuran :attribute maksimal :max kilobyte.',
        'numeric' => ':attribute maksimal :max.',
        'string' => ':attribute maksimal :max karakter.',
    ],
    'max_digits' => ':attribute tidak boleh lebih dari :max digit.',
    'mimes' => 'Format :attribute harus salah satu dari: :values.',
    'mimetypes' => 'Format :attribute harus salah satu dari: :values.',
    'min' => [
        'array' => ':attribute minimal harus berisi :min item.',
        'file' => 'Ukuran :attribute minimal :min kilobyte.',
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
    ],
    'min_digits' => ':attribute minimal harus :min digit.',
    'missing' => ':attribute wajib tidak ada.',
    'missing_if' => ':attribute wajib tidak ada ketika :other bernilai :value.',
    'missing_unless' => ':attribute wajib tidak ada kecuali :other bernilai :value.',
    'missing_with' => ':attribute wajib tidak ada ketika :values ada.',
    'missing_with_all' => ':attribute wajib tidak ada ketika :values ada.',
    'multiple_of' => ':attribute harus kelipatan dari :value.',
    'not_in' => ':attribute yang dipilih tidak valid.',
    'not_regex' => 'Format :attribute tidak valid.',
    'numeric' => ':attribute harus berupa angka.',
    'password' => [
        'letters' => ':attribute harus berisi minimal satu huruf.',
        'mixed' => ':attribute harus berisi huruf besar dan kecil.',
        'numbers' => ':attribute harus berisi minimal satu angka.',
        'symbols' => ':attribute harus berisi minimal satu simbol.',
        'uncompromised' => ':attribute pernah muncul di kebocoran data. Gunakan :attribute lain.',
    ],
    'present' => ':attribute wajib ada.',
    'present_if' => ':attribute wajib ada ketika :other bernilai :value.',
    'present_unless' => ':attribute wajib ada kecuali :other bernilai :value.',
    'present_with' => ':attribute wajib ada ketika :values ada.',
    'present_with_all' => ':attribute wajib ada ketika :values ada.',
    'prohibited' => ':attribute tidak diizinkan diisi.',
    'prohibited_if' => ':attribute tidak diizinkan diisi ketika :other bernilai :value.',
    'prohibited_if_accepted' => ':attribute tidak diizinkan diisi ketika :other disetujui.',
    'prohibited_if_declined' => ':attribute tidak diizinkan diisi ketika :other ditolak.',
    'prohibited_unless' => ':attribute tidak diizinkan diisi kecuali :other termasuk :values.',
    'prohibits' => ':attribute membuat :other tidak boleh diisi.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':attribute wajib diisi.',
    'required_array_keys' => ':attribute harus berisi entri untuk: :values.',
    'required_if' => ':attribute wajib diisi ketika :other bernilai :value.',
    'required_if_accepted' => ':attribute wajib diisi ketika :other disetujui.',
    'required_if_declined' => ':attribute wajib diisi ketika :other ditolak.',
    'required_unless' => ':attribute wajib diisi kecuali :other termasuk :values.',
    'required_with' => ':attribute wajib diisi ketika :values ada.',
    'required_with_all' => ':attribute wajib diisi ketika :values ada.',
    'required_without' => ':attribute wajib diisi ketika :values tidak ada.',
    'required_without_all' => ':attribute wajib diisi ketika tidak ada satupun dari :values.',
    'same' => ':attribute harus sama dengan :other.',
    'size' => [
        'array' => ':attribute harus berisi :size item.',
        'file' => 'Ukuran :attribute harus :size kilobyte.',
        'numeric' => ':attribute harus :size.',
        'string' => ':attribute harus :size karakter.',
    ],
    'starts_with' => ':attribute harus diawali dengan salah satu dari: :values.',
    'string' => ':attribute harus berupa teks.',
    'timezone' => ':attribute harus berupa zona waktu yang valid.',
    'unique' => ':attribute sudah digunakan.',
    'uploaded' => ':attribute gagal diunggah.',
    'uppercase' => ':attribute harus berupa huruf besar.',
    'url' => ':attribute bukan URL yang valid.',
    'ulid' => ':attribute bukan ULID yang valid.',
    'uuid' => ':attribute bukan UUID yang valid.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Field/rule combinations where the generic Indonesian sentence above
    | still reads awkwardly for the phrase this app actually uses.
    |
    */

    'custom' => [
        'current_password' => [
            'required' => 'Kata sandi saat ini wajib diisi.',
            'current_password' => 'Kata sandi saat ini salah.',
        ],
        'password' => [
            'confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ],
        'end' => [
            'after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
        ],
        'end_date' => [
            'after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
        ],
        'end_time' => [
            'after' => 'Jam selesai harus setelah jam mulai.',
        ],
        'trip_end_date' => [
            'after_or_equal' => 'Tanggal selesai perjalanan tidak boleh sebelum tanggal mulai.',
        ],
        'requested_clock_in' => [
            'required_without' => 'Isi jam masuk atau jam pulang.',
        ],
        'requested_clock_out' => [
            'required_without' => 'Isi jam masuk atau jam pulang.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Swaps the raw field name in every message above ("category") for a
    | human label ("kategori"), across every Api controller at once.
    |
    */

    'attributes' => [
        'message' => 'Pesan',
        'conversation_id' => 'Percakapan',
        'pack_id' => 'Paket token',
        'body' => 'Isi',
        'type' => 'Jenis',
        'work_mode' => 'Mode kerja',
        'latitude' => 'Lokasi',
        'longitude' => 'Lokasi',
        'clocked_at' => 'Waktu absen',
        'selfie' => 'Selfie',
        'date' => 'Tanggal',
        'requested_clock_in' => 'Jam masuk',
        'requested_clock_out' => 'Jam pulang',
        'reason' => 'Alasan',
        'email' => 'Email',
        'password' => 'Kata sandi',
        'current_password' => 'Kata sandi saat ini',
        'challenge_token' => 'Sesi verifikasi',
        'amount' => 'Nominal',
        'purpose' => 'Tujuan',
        'needed_date' => 'Tanggal dibutuhkan',
        'name' => 'Nama',
        'file' => 'Berkas',
        'branch_id' => 'Cabang',
        'visit_date' => 'Tanggal kunjungan',
        'location' => 'Lokasi',
        'tasks' => 'Daftar tugas',
        'task_before' => 'Foto sebelum',
        'task_after' => 'Foto sesudah',
        'photos' => 'Foto',
        'after' => 'Foto sesudah',
        'status' => 'Status',
        'end' => 'Tanggal akhir',
        'start' => 'Tanggal mulai',
        'leave_type_id' => 'Jenis cuti',
        'start_date' => 'Tanggal mulai',
        'end_date' => 'Tanggal akhir',
        'title' => 'Judul',
        'segments' => 'Transkrip',
        'audio' => 'Berkas audio',
        'mood' => 'Mood',
        'action' => 'Aksi',
        'ids' => 'Data yang dipilih',
        'employee_id' => 'Karyawan',
        'is_done' => 'Status selesai',
        'day_type' => 'Jenis hari',
        'start_time' => 'Jam mulai',
        'end_time' => 'Jam selesai',
        'category' => 'Kategori',
        'expense_date' => 'Tanggal pengeluaran',
        'receipt' => 'Struk',
        'code' => 'Kode',
        'device_id' => 'ID perangkat',
        'fcm_token' => 'Token notifikasi',
        'submission_date' => 'Tanggal pengajuan',
        'trip_start_date' => 'Tanggal mulai perjalanan',
        'trip_end_date' => 'Tanggal selesai perjalanan',
        'items' => 'Item',
        'documents' => 'Dokumen',
        'target_id' => 'Karyawan tujuan',
        'nominee_employee_id' => 'Karyawan yang dinominasikan',
        'eotm_core_value_id' => 'Nilai inti',
        'social_category_id' => 'Kategori',
        'image' => 'Foto',
        'remove_image' => 'Hapus foto',
        'parent_id' => 'Komentar induk',
        'per_page' => 'Jumlah per halaman',
    ],

];

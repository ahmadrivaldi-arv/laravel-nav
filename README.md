# Laravel Navigation

Core navigasi Laravel dengan fluent definitions, authorization, dan hasil tree immutable. Package `ahmdrv/laravel-nav` menggunakan namespace `Ahmdrv\Navigation`. Mendukung PHP 8.3+ dan Laravel 10, 11, 12, serta 13. Constraint Illuminate adalah `^10.0 || ^11.0 || ^12.0 || ^13.0`; versi major berikutnya perlu diuji sebelum ditambahkan.

Package hanya menghasilkan data. Rendering HTML, Blade, API response, orientasi menu, layout, escaping, dan sanitization ditangani aplikasi. Tidak ada views/components, assets, view composers, database menu, maupun dependency wajib Livewire, Filament, atau Spatie Permission.

## Instalasi

Setelah package tersedia di repository Composer Anda:

```bash
composer require ahmdrv/laravel-nav
php artisan vendor:publish --tag="nav-config"
```

Laravel menemukan `NavigationServiceProvider` dan alias `Navigation` melalui package discovery. Facade lengkapnya:

```php
use Ahmdrv\Navigation\Facades\Navigation;
```

Konfigurasi `config/navigation.php`:

```php
return [
    'registry' => base_path('routes/navigation.php'),
];
```

File yang tidak ada diabaikan. Set `registry` ke `null` untuk menonaktifkan file registry, atau ganti dengan absolute path. Konfigurasi dibaca ketika manager pertama kali di-resolve.

## Registrasi dan konsumsi

Di `boot()` service provider aplikasi:

```php
use Ahmdrv\Navigation\Facades\Navigation;
use App\Models\Invoice;

Navigation::register([
    Navigation::group('Finance')
        ->sort(40)
        ->items([
            Navigation::item('Invoices')
                ->icon('tabler tabler-file-invoice')
                ->route('invoices.index')
                ->activeRoute('invoices.*')
                ->can('viewAny', Invoice::class),
        ]),
]);
```

`item()` dan `group()` hanya membuat definitions. Hanya `register()` yang menambah root; children tidak otomatis menjadi root. `register()` menerima list definitions dan mengembalikan `void`. Setiap panggilan menambahkan roots dalam urutan panggilan.

Alternatif file `routes/navigation.php` **mengembalikan list**:

```php
<?php

declare(strict_types=1);

use Ahmdrv\Navigation\Facades\Navigation;

return [
    Navigation::item('Dashboard')->route('dashboard')->sort(-10),
    Navigation::item('Help')->url('https://example.com/help')->openUrlInNewTab(),
];
```

Jangan memanggil `register()` atau `build()` di file itu. Gunakan salah satu tempat registrasi untuk definitions yang sama agar tidak diduplikasi. Registrasi programatis dan file dapat digabungkan untuk definitions berbeda.

Konsumsi di controller aplikasi:

```php
$items = Navigation::build(); // list<ResolvedNavigationItem>
return view('dashboard', ['navigation' => $items]);
```

Atau bentuk array untuk API Resource/response milik aplikasi:

```php
$data = array_map(static fn ($item) => $item->toArray(), Navigation::build());
return response()->json(['navigation' => $data]);
```

Contoh ini bukan renderer package. Lihat [contoh consumer](examples/consume.php).

## Definisi fluent

Semua setter mengembalikan definition yang sama. Definitions mutable saat disusun; builder tidak mengubah definitions tersebut. Label selalu teks, termasuk string yang kebetulan berisi tag HTML. Tidak ada dukungan label trusted HTML.

| Metode item | Perilaku |
| --- | --- |
| `label(string)` | Mengganti teks label |
| `icon(string\|Icon)` | Metadata icon; string menjadi identifier/class |
| `route(string, array = [])` | Named route beserta parameters dan query Laravel |
| `url(?string)` | URL literal; `null` diperbolehkan; `'#'` dipertahankan |
| `badge(label, color = 'primary')` | Label scalar/null/Closure dan warna string/Closure |
| `openUrlInNewTab(bool\|Closure = true)` | Target `_blank` bila benar, selain itu `null` |
| `sort(int)` | Urutan ascending, default `0` |
| `items(array\|Closure)` | List children atau callback yang menghasilkan list |
| `activeRoute(string\|array)` | Satu/beberapa pola nama route, termasuk `*` |
| `activeWhen(Closure)` | Kondisi aktif tambahan |
| `visible(bool\|Closure = true)` | Semua visible rules harus benar |
| `hidden(bool\|Closure = true)` | Tidak satu pun hidden rules boleh benar |
| `roles(string\|array)` | Minimal satu role per panggilan cocok |
| `permissions(string\|array)` | Minimal satu permission per panggilan cocok |
| `can(string, mixed = [])` | Laravel Gate/policy, termasuk class/model arguments |
| `authorize(Closure)` | Kondisi authorization tambahan |

Group mendukung `label`, `sort`, `items`, `visible`, `hidden`, `roles`, `permissions`, `can`, dan `authorize`. Groups adalah struktur pengelompokan tanpa URL, badge, icon, atau active condition sendiri.

Pemanggilan setter nilai (`route`, `url`, `items`, `badge`, `activeRoute`, `activeWhen`, dll.) mengganti nilai sebelumnya. `route()` dan `url()` saling menggantikan; tujuan terakhir menang. Rules visibility/authorization ditambahkan secara kumulatif, termasuk pemanggilan berulang metode yang sama. `roles([])` dan `permissions([])` selalu menolak.

## Authorization dan guest

Filtering berjalan sebelum children, URL, active state, target, atau badge dievaluasi. Semua kategori aturan harus lolos: visible, hidden, roles, permissions, Gate, dan authorize. Evaluasi dapat berhenti segera setelah satu aturan menolak. Parent yang ditolak tidak mengevaluasi atau mengekspos children. Group kosong setelah filtering dihapus; item tanpa children tetap dipertahankan.

Guest mendapatkan `null` sebagai user. Guest dapat melihat item publik, ditolak oleh roles/permissions, dan mengikuti perilaku Laravel Gate untuk callback/policy yang menerima nullable user. `authorize()` dan visibility callbacks menentukan sendiri apakah guest boleh lewat.

Default `DenyRolePermissionAdapter` menolak semua aturan role/permission, juga untuk authenticated user. Tidak ada deteksi otomatis metode model maupun fallback yang mengizinkan akses. Bind adapter di `register()` service provider aplikasi:

```php
use Ahmdrv\Navigation\Contracts\RolePermissionAdapter;

$this->app->bind(RolePermissionAdapter::class, AppRolePermissionAdapter::class);
```

Adapter harus mengimplementasikan:

```php
public function hasAnyRole(Authenticatable $user, array $roles): bool;
public function hasAnyPermission(Authenticatable $user, array $permissions): bool;
```

Contoh adapter aplikasi yang sudah memakai Spatie Permission:

```php
use Ahmdrv\Navigation\Contracts\RolePermissionAdapter;
use Illuminate\Contracts\Auth\Authenticatable;

final class AppRolePermissionAdapter implements RolePermissionAdapter
{
    public function hasAnyRole(Authenticatable $user, array $roles): bool
    {
        return method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles);
    }

    public function hasAnyPermission(Authenticatable $user, array $permissions): bool
    {
        return method_exists($user, 'hasAnyPermission') && $user->hasAnyPermission($permissions);
    }
}
```

Package tidak memasang Spatie Permission. Adapter dapat memakai implementasi role/permission lain. Adapter harus stateless atau memiliki lifecycle request yang tepat. Implementasi lengkap aturan dapat diganti melalui contract `NavigationAuthorization`. Visibility navigasi tidak menggantikan authorization pada endpoint aplikasi.

## Callback dan badges

Semua callback memakai `CallbackEvaluator`, berdasarkan posisi parameter, tanpa dependency injection berdasarkan nama/type parameter.

| Callback | Argumen yang tersedia, berurutan |
| --- | --- |
| `items`, `visible`, `hidden`, `authorize`, `activeWhen`, `openUrlInNewTab`, badge label | `?Authenticatable $user`, `NavigationNode $item`, `NavigationContext $context` |
| badge color | `string\|int\|float $label`, `?Authenticatable $user`, `NavigationItem $item`, `NavigationContext $context` |

Closure boleh menerima nol argumen atau prefix signature tersebut. Argumen opsional yang posisinya tersedia tetap diisi; parameter opsional setelah seluruh argumen tersedia memakai default PHP. Variadic menerima seluruh argumen tersisa. Parameter required melebihi jumlah yang didukung menghasilkan `InvalidArgumentException`. Callback exception diteruskan; callback harus read-only, tidak melakukan registrasi/mutasi definitions.

```php
Navigation::item('Inbox')->badge(
    fn ($user, $item) => $user?->unreadNotifications()->count(),
    fn ($label, $user, $item) => $label > 10 ? 'danger' : 'primary',
);
```

Badge disembunyikan untuk `null`, `false`, `''`, `0`, `0.0`, dan `'0'`. Label yang tampil harus string, integer, atau float; tipe lain (termasuk `true`) menghasilkan `InvalidArgumentException`. Warna hasil callback harus string. Warna hanya metadata: `danger`, `#ff5722`, dan `var(--brand-color)` dipertahankan apa adanya.

Callback label dipanggil sekali per item yang lolos dalam satu build; warna hanya dievaluasi sekali jika label tampil. Callback children/active/target masing-masing dievaluasi sekali saat diperlukan. Aturan dengan Closure yang sama pada beberapa kategori tetap merupakan aturan terpisah. Definition yang sengaja digunakan pada beberapa posisi dievaluasi untuk setiap kemunculannya. Build berikutnya mengevaluasi kembali; tidak ada automatic database caching atau cache hasil per user.

## URL, active/open, dan sorting

Named routes di-resolve saat build, sesudah routes aplikasi tersedia. Route parameters memakai URL generator Laravel, termasuk implicit route key dan query parameters tambahan. Named route yang tidak ditemukan meneruskan `Symfony\Component\Routing\Exception\RouteNotFoundException`; parameter required yang hilang mengikuti exception URL generator Laravel. Item tersembunyi tidak me-resolve route.

Tanpa `activeRoute()`, named destination menjadi pola exact nama route. URL literal tidak otomatis aktif. Pola eksplisit memakai Laravel `Str::is`, mendukung beberapa pola dan wildcard. `activeWhen()` digabung dengan hasil pola menggunakan OR dan selalu dievaluasi sekali bila disediakan. Tanpa current named route, hanya `activeWhen()` yang dapat mengaktifkan item.

`isActive` hanya status item itu sendiri. Descendant aktif **tidak** membuat parent `isActive`. `isOpen` benar jika item itu aktif atau ada descendant aktif/terbuka. Group selalu `isActive = false`, tetapi dapat `isOpen = true` melalui children.

Setiap tingkat diurutkan berdasarkan `sort` ascending. Default `0`; nilai sama mempertahankan urutan registrasi/list. Urutan definitions sumber tidak diubah. Siklus atau kedalaman melebihi 128 tingkat menghasilkan `LogicException`.

## Icon metadata

```php
use Ahmdrv\Navigation\Data\Icon;

Navigation::item('Home')->icon('tabler tabler-home');
Navigation::item('Home')->icon(Icon::identifier('home'));
Navigation::item('Logo')->icon(Icon::trustedSvg('<svg>...</svg>'));
```

Identifier maupun CSS class memiliki representasi `['type' => 'identifier', 'value' => '...']`. SVG opt-in memakai `['type' => 'trusted_svg', 'value' => '...']`. Core tidak membungkus icon dalam tag HTML, merender Blade, atau memvalidasi SVG. Renderer bertanggung jawab untuk escaping label dan identifier serta sanitization/trust policy markup. Jangan meneruskan input pengguna sebagai trusted SVG tanpa sanitization.

## Kontrak output

`build(?NavigationContext $context = null): array` mengembalikan `list<ResolvedNavigationItem>`. Tanpa argumen, context terbaru diambil dari request Laravel. `NavigationContext` menyediakan `user(): ?Authenticatable`, `routeName(): ?string`, dan `request(): Request`; implementasi custom dapat diberikan pada `build()` atau melalui binding container.

DTO dan metadata icon/badge adalah `final readonly`. Children adalah array readonly berisi DTO readonly secara rekursif; array sumber disalin tanpa reference alias. Root array milik consumer boleh disusun ulang tanpa memengaruhi registry. Tidak ada Closure, query builder, model user, atau authorization yang belum dievaluasi dalam hasil.

`toArray()` melakukan konversi rekursif dengan key yang sama pada item maupun group:

```php
[
    'kind' => 'item', // 'item' | 'group'
    'label' => 'Inbox', // string teks
    'icon' => ['type' => 'identifier', 'value' => 'mail'], // atau null / trusted_svg
    'url' => 'https://example.com/inbox', // string|null
    'target' => '_blank', // '_blank'|null
    'badge' => ['label' => 3, 'color' => 'danger'], // atau null
    'sort' => 0, // int
    'isActive' => true, // bool
    'isOpen' => true, // bool
    'children' => [], // list dengan schema yang sama
]
```

Group memiliki icon/url/target/badge `null`. Tidak ada HTML buatan core, CSS state classes, atau key layout.

## Lifecycle dan worker

`NavigationManager` merupakan singleton registry definitions untuk satu aplikasi/worker. Registrasi programatis dilakukan sekali di provider saat bootstrap, bukan di middleware/controller setiap request. File registry dimuat lazy satu kali saat build pertama, ditambahkan setelah roots programatis yang sudah ada. File harus menghasilkan list; hasil invalid menimbulkan `InvalidArgumentException` dan loading dapat dicoba ulang setelah diperbaiki. File hilang dianggap selesai dimuat; restart worker untuk mengambil perubahan registry/config.

Builder, context, dan default authorization di-resolve ulang setiap build dari container aktif. Tidak ada user, hasil Gate, active state, atau badge yang disimpan dalam registry. Build berulang menghasilkan DTO baru dan tidak menambah roots. Tidak ada global result cache atau serialization Closure.

Definitions yang bertahan dalam worker harus berisi nilai statis atau callback yang membaca argumen context saat evaluasi. Jangan menangkap `auth()->user()`, request, model user, atau hasil query saat bootstrap; jangan mengikat custom context/adapter yang menangkap request sebagai singleton. Jika definitions perlu berbeda per user, gunakan `visible`, `authorize`, atau `items(Closure)`; jangan memanggil `register()` per request. Package tidak dapat menghapus state yang sengaja ditangkap callback aplikasi.

Pengujian mengganti request dan user dalam container/proses yang sama. Server Octane sesungguhnya belum dijalankan dalam verifikasi lokal ini.

## Pengembangan dan verifikasi

```bash
composer install
composer format
composer analyse
composer test
composer validate --strict
```

Pint memakai preset PSR-12 dengan strict types. PHPStan/Larastan level 6 tanpa baseline. Test memakai PHPUnit 10/11 dan Orchestra Testbench 8–11 sesuai versi Laravel, tanpa database bisnis. CI menjalankan test matrix, formatting, static analysis, Dependabot, dan pembaruan changelog.

Matrix kompatibilitas:

| Laravel | PHP yang diuji di CI | Testbench | PHPUnit |
| --- | --- | --- | --- |
| 10 | 8.3 | 8 | 10 |
| 11 | 8.3, 8.4 | 9 | 11 |
| 12 | 8.3, 8.4, 8.5 | 10 | 11 |
| 13 | 8.3, 8.4, 8.5 | 11 | 11 |

Setiap kombinasi dijalankan pada Linux dan Windows, dengan dependency `prefer-lowest` dan `prefer-stable`. PHP 8.1/8.2 tidak didukung package ini.

Verifikasi lokal pada PHP 8.3.21 memakai kombinasi berikut; setiap versi menjalankan 51 tests (137 assertions) dan static analysis level 6:

| Laravel | Testbench | PHPUnit |
| --- | --- | --- |
| 10.50.3 | 8.39.0 | 10.5.66 |
| 11.57.0 | 9.18.0 | 11.5.57 |
| 12.70.0 | 10.12.0 | 11.5.57 |
| 13.38.0 | 11.3.0 | 11.5.57 |

Laravel 10 dan 11 sudah melewati masa dukungan keamanan upstream. Kompatibilitas package tidak memperpanjang dukungan keamanan framework. Job CI Laravel 10/11 mengizinkan dependency dengan advisory untuk menjalankan test kompatibilitas dan tetap menampilkan hasil audit; pengecualian ini hanya berlaku di lingkungan CI, tidak disertakan dalam konfigurasi Composer package. Job Laravel 12/13 menggunakan kebijakan keamanan default Composer. Lihat [kebijakan dukungan Laravel](https://laravel.com/docs/13.x/releases#support-policy).


## Lisensi

MIT. Lihat [LICENSE.md](LICENSE.md).

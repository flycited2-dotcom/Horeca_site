> Раздел ТЗ horeca-shop. Версия, оглавление, история изменений и карта «спринт → разделы» — [TZ-horeca-shop.md](../../TZ-horeca-shop.md).

# 4. Архитектура и структура каталогов

```
app/
  Actions/
    Cart/          AddToCart, UpdateCartItemQty, RemoveCartItem, RestoreCartItem, MergeGuestCart
    Orders/        CreateOrderFromCart, RepeatOrder, ChangeOrderStatus, GenerateOrderNumber
    Pricing/       CalculateCartTotals
    Leads/         CreateLead
    Companies/     RegisterWholesaleCompany, ApproveCompany, UpdateCompanyDetails
    BulkOrder/     ParseBulkOrderList, AddBulkOrderToCart
    Favorites/     ToggleFavorite, MergeGuestFavorites
  Console/Commands/
    SupplierImportCommand       php artisan supplier:import {profile} {--dry-run} {--force}
    GenerateSitemapCommand, RecalculatePopularityCommand, PruneCartsCommand,
    SendImportDigestCommand, MailTestCommand, AnonymizeUserCommand
  Enums/
    UserRole, CompanyStatus, CompanySegment, Availability, WarehouseStockStatus,
    OrderStatus, OrderType, DeliveryMethod, PaymentMethod, LeadType, LeadStatus,
    PriceKind, ImportRunStatus, ImportTrigger, ImportEntity, SupplierRefEntity,
    AttributeType, AttributeValueSource
  Events/          OrderCreated, CompanyApproved, ImportFinished, ImportFailed
  Filament/
    Resources/     Product, Category, Brand, Collection, Order, Lead, User, Company, PriceTier,
                   Supplier, SupplierRef, Warehouse, ImportProfile, ImportRun, Attribute, Page, Redirect
    Pages/         Dashboard, Settings
    Widgets/       OrdersStats, NewOrders, NewLeads, PendingCompanies, LatestImports, UnmappedSupplierRefs
  Http/
    Controllers/   Home, Catalog, Product, Brand, Search, Cart, Checkout, Lead, Wholesale,
                   Account, AccountOrder, AccountCompany, PriceList, Favorite, Page, Sitemap, Robots
    Middleware/    EnsureWholesaleApproved, TrackUtm, SecurityHeaders
    Requests/      CheckoutRequest, LeadRequest, WholesaleRegisterRequest, CompanyUpdateRequest, BulkOrderRequest
  Jobs/            RunSupplierImport, DownloadProductImages, SendTelegramMessage
  Listeners/       SendOrderNotifications, SendCompanyApprovedNotification, SendImportFailedAlert
  Livewire/
    Catalog/       ProductFilter, ProductGrid
    Cart/          CartCounter, CartPage, AddToCartButton
    Search/        HeaderSearch
    Account/       BulkOrder
    Leads/         LeadForm
  Mail/, Notifications/
  Models/          см. §5
  Observers/       ProductObserver (locked_fields, редиректы при смене slug), CatalogCacheObserver
  Policies/        OrderPolicy, CompanyPolicy, CartPolicy, FavoritePolicy
  Services/
    Supplier/
      Contracts/   SupplierFeedInterface
      Data/        FeedCapabilities, FetchedSource, SupplierCategory, SupplierProduct,
                   SupplierStock, SupplierImage, SupplierAttribute
      Sources/Rosholod/  RosholodCatalogXmlSource, RosholodStockXmlSource, RosholodApiSource (спринт 7)
      Xml/         Cp1251XmlReader
      Import/      ImportRunner, SourceRegistry, StagingWriter, ThresholdGuard, CategorySync,
                   BrandSync, ProductUpserter, StockUpserter, AvailabilityCalculator,
                   AttributeValueParser, StockValueMapper
    Catalog/       CatalogQuery, CatalogCache
    Pricing/       PriceResolver, Price
    Search/        SearchEngineInterface, DatabaseSearchEngine, QueryNormalizer
    Notify/        TelegramNotifier
    Seo/           MetaBuilder, SchemaOrg
    Payments/      PaymentGatewayInterface
  Support/         Money, Percent, Slugger, Phone, Typography
config/suppliers/rosholod.php     карта тегов XML, адреса выгрузок, карта значений остатков
resources/
  css/app.css                     Tailwind 4 + токены в @theme
  js/app.js
  views/
    components/                   Blade-компоненты дизайн-системы (x-product-card, x-price, x-availability, x-button…)
    layouts/, home/, catalog/, product/, brands/, search/, cart/, checkout/, wholesale/,
    account/, pages/, errors/, emails/
lang/ru/
routes/web.php, routes/console.php
database/migrations, database/seeders, database/factories
tests/Feature, tests/Unit, tests/Fixtures/rosholod/
storage/app/imports/{supplier}/
docs/                             отчёты по данным, архив версий документов
```

**Слои.** Контроллер, Livewire-компонент или Filament-ресурс → Action или Service → модель. Бизнес-логика в контроллерах, Livewire и Filament запрещена. Выборки витрины — через `CatalogQuery`.

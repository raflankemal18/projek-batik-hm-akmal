/**
 * Konstanta data-testid.
 *
 * Satu sumber kebenaran antara spec dan JSX: kalau ada elemen yang diubah di
 * resources/js, update daftar ini, bukan string-nya inline di spec.
 */
export const TID = {
    // Auth
    registerName: 'register-name',
    registerEmail: 'register-email',
    registerPassword: 'register-password',
    registerPasswordConfirmation: 'register-password-confirmation',
    registerSubmit: 'register-submit',
    loginEmail: 'login-email',
    loginPassword: 'login-password',
    loginSubmit: 'login-submit',
    logout: 'logout',
    adminProfileTrigger: 'admin-profile-trigger',
    adminLogout: 'admin-logout',

    // Katalog
    headerSearch: 'header-search',
    categoryTab: 'category-tab',
    productCardLink: 'product-card-link',
    productCardCartButton: 'product-card-cart-button',

    // Detail produk
    sizeOption: 'size-option',
    addToCart: 'add-to-cart',

    // Keranjang & checkout
    cartCheckout: 'cart-checkout',
    checkoutPhone: 'checkout-phone',
    checkoutAddress: 'checkout-address',
    checkoutMessage: 'checkout-message',
    checkoutSubmit: 'checkout-submit',

    // Pembayaran
    unpaidOrder: 'unpaid-order',
    startPayment: 'start-payment',

    // Admin
    adminProductCreate: 'admin-product-create',
    adminProductName: 'admin-product-name',
    adminProductPrice: 'admin-product-price',
    adminProductDescription: 'admin-product-description',
    adminProductCategory: 'admin-product-category',
    adminProductSize: 'admin-product-size',
    adminProductStock: 'admin-product-stock',
    adminProductSubmit: 'admin-product-submit',
    adminProductRow: 'admin-product-row',
    fulfillmentSelect: 'fulfillment-select',
    fulfillmentResi: 'fulfillment-resi',
    fulfillmentCourier: 'fulfillment-courier',
} as const;
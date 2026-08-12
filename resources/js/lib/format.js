export function formatRupiah(amount) {
    const number = typeof amount !== "number" ? parseInt(amount) : amount;

    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(number);
}

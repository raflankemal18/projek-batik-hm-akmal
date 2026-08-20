export function formatRupiah(amount) {
    // Pastikan input adalah sebuah number, jika tidak, convert
    const number = typeof amount !== "number" ? parseInt(amount) : amount;

    // Format angka ke rupiah
    const formatted = new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0, // Opsional: tidak menampilkan sen
    }).format(number);

    return formatted;
}

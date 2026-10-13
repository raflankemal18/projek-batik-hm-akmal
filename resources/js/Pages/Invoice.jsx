import { formatRupiah } from "@/lib/format";
import React from "react";

export default function Invoice({ payment }) {
    const order = payment.order || {};
    const details = order.details || [];
    const totalHarga = payment.total_harga || 0;

    return (
        <>
            <div className="bg-white border rounded-lg shadow-lg px-6 py-8 max-w-md mx-auto mt-8">
                <h1 className="font-bold text-2xl my-4 text-center text-blue-600">
                    Invoice
                </h1>
                <hr className="mb-2" />
                <div className="flex justify-between mb-6">
                    <h1 className="text-lg font-bold">Invoice</h1>
                    <div className="text-gray-700">
                        <div>
                            Date: {new Date(payment.created_at).toLocaleDateString()}
                        </div>
                        <div>Invoice #: {payment.transaction_id || payment.id}</div>
                    </div>
                </div>
                <div className="mb-8">
                    <h2 className="text-lg font-bold mb-4">Bill To:</h2>
                    <div className="text-gray-700 mb-2">
                        {order.name || payment.user?.name}
                    </div>
                    <div className="text-gray-700 mb-2">
                        {order.alamat}
                    </div>
                    <div className="text-gray-700 mb-2">
                        Phone: {order.phone}
                    </div>
                    <div className="text-gray-700 mb-2">
                        Email: {order.email || payment.user?.email}
                    </div>
                    <div className="text-gray-700 mt-4">
                        Status : {payment.status}
                    </div>
                </div>
                <table className="w-full mb-8">
                    <thead>
                        <tr>
                            <th className="text-left font-bold text-gray-700">
                                Description
                            </th>
                            <th className="text-right font-bold text-gray-700">
                                Amount
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {details.map((item) => (
                            <tr key={item.id}>
                                <td className="text-left text-gray-700">
                                    {item.product?.name || "Product"}{" "}
                                    <p className="text-sm text-gray-500">
                                        {/* motif_name dipakai, bukan nama motif di katalog,
                                            supaya invoice lama tetap benar setelah motif
                                            diganti namanya. */}
                                        ({item.motif_name ? `Motif: ${item.motif_name}` : `Size: ${item.size}`}, Qty: {item.quantity})
                                    </p>
                                </td>
                                <td className="text-right text-gray-700">
                                    {formatRupiah(item.harga * item.quantity)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td className="text-left font-bold text-gray-700 pt-4">
                                Total
                            </td>
                            <td className="text-right font-bold text-gray-700 pt-4">
                                {formatRupiah(totalHarga)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <div className="text-center mt-8">
                    <button onClick={() => window.print()} className="bg-blue-600 text-white px-4 py-2 rounded no-print">Cetak</button>
                </div>
                <div className="text-gray-700 mt-4 text-center">
                    Thank you for your business!
                </div>
            </div>
            <style>{`@media print { .no-print { display: none; } body { background: white; } }`}</style>
        </>
    );
}

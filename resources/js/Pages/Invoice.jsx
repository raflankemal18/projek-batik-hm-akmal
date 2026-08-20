import { formatRupiah } from "@/helper/formatRupiah";
import React from "react";

export default function Invoice({ payment, status }) {
    const firstPayment = payment[0];
    const statuspembayaran = status[0];

    const calculateTotal = (items) => {
        return items.reduce(
            (total, item) => total + parseFloat(item.harga) * item.quantity,
            0
        );
    };
    const totalHarga = calculateTotal(payment);
    return (
        <>
            <div className="bg-white border rounded-lg shadow-lg px-6 py-8 max-w-md mx-auto mt-8">
                <h1 className="font-bold text-2xl my-4 text-center text-blue-600">
                    Invoice{" "}
                </h1>
                <hr className="mb-2" />
                <div className="flex justify-between mb-6">
                    <h1 className="text-lg font-bold">Invoice</h1>
                    <div className="text-gray-700">
                        <div>
                            Date:{" "}
                            {new Date(
                                firstPayment.created_at
                            ).toLocaleDateString()}
                        </div>
                        <div>Invoice #: INV{firstPayment.payment_id}</div>
                    </div>
                </div>
                <div className="mb-8">
                    <h2 className="text-lg font-bold mb-4">Bill To:</h2>
                    <div className="text-gray-700 mb-2">
                        {firstPayment.name}
                    </div>
                    <div className="text-gray-700 mb-2">
                        {firstPayment.alamat}
                    </div>
                    <div className="text-gray-700 mb-2">
                        Phone: {firstPayment.phone}
                    </div>
                    <div className="text-gray-700">
                        Email: {firstPayment.email}
                    </div>
                    {/* {status.map((item) => ( */}
                    <div className="text-gray-700 mt-4">
                        Status : {statuspembayaran.status}
                    </div>
                    {/* ))} */}
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
                        {/* Mapping over the payment array in case of multiple items */}
                        {payment.map((item) => (
                            <tr key={item.id}>
                                <td className="text-left text-gray-700">
                                    {item.product.name}{" "}
                                    <p>
                                        (Size: {item.size}, Quantity:{" "}
                                        {item.quantity})
                                    </p>
                                </td>
                                <td className="text-right text-gray-700">
                                    {formatRupiah(item.harga)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td className="text-left font-bold text-gray-700">
                                Total
                            </td>
                            <td className="text-right font-bold text-gray-700">
                                {formatRupiah(totalHarga)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <div className="text-gray-700 mb-2">
                    Thank you for your business!
                </div>
            </div>
        </>
    );
}

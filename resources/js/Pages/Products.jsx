import React from "react";
import WelcomeLayout from "@/Layouts/WelcomeLayout";
import ProductCollection from "@/Components/ProductCollection";
import { Head } from "@inertiajs/react";

export default function Products({ products }) {
    return (
        <WelcomeLayout>
            <Head title="Koleksi Batik - BatikNesia" />
            
            {/* Render Component Koleksi dengan Filter */}
            <ProductCollection products={products} />
            
        </WelcomeLayout>
    );
}
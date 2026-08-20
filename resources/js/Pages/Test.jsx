import React from "react";
import WelcomeLayout from "@/Layouts/WelcomeLayout";
import ModernDetail from "@/Components/ModernDetail"; // Import component baru
import { Head } from "@inertiajs/react";

export default function Test({ products }) {
    return (
        <WelcomeLayout>
            <Head title={`${products.name} - Koleksi BatikNesia`} />
            
            {/* Render Component Detail Modern */}
            <ModernDetail product={products} />
            
        </WelcomeLayout>
    );
}
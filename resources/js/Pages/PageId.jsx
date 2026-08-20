import Header from "@/Components/Header";
import DetailPage from "@/Components/pagewelcome/DetailPage";
import WelcomeLayout from "@/Layouts/WelcomeLayout";
import React from "react";

export default function PageId({ products }) {
    return (
        <WelcomeLayout>
            <DetailPage products={products} />
        </WelcomeLayout>
    );
}

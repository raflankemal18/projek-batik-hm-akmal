import ProductModal from "./modal/Modal";

export default function ProductForm() {
    return (
        <div>
            {/* Langsung render modal baru yang sudah ada trigger button-nya */}
            <ProductModal />
        </div>
    );
}
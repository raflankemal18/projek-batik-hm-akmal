import React from "react";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from "@/Components/ui/dialog";
import { Button } from "@/Components/ui/button";
import { AlertTriangle } from "lucide-react";

export default function ConfirmDialog({ 
    isOpen, 
    onClose, 
    onConfirm, 
    title = "Konfirmasi Hapus", 
    description = "Apakah Anda yakin? Tindakan ini tidak dapat dibatalkan.",
    confirmText = "Ya, Hapus",
    cancelText = "Batal",
    variant = "danger" // danger | info
}) {
    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[425px]">
                <div className="flex flex-col items-center text-center gap-4 py-4">
                    <div className={`p-3 rounded-full ${variant === 'danger' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600'}`}>
                        <AlertTriangle className="w-8 h-8" />
                    </div>
                    
                    <DialogHeader>
                        <DialogTitle className="text-xl font-serif font-bold text-batik-900 text-center">
                            {title}
                        </DialogTitle>
                        <DialogDescription className="text-center text-gray-500">
                            {description}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <DialogFooter className="flex gap-3 sm:justify-center w-full mt-2">
                    <Button 
                        variant="outline" 
                        onClick={onClose}
                        className="flex-1 border-gray-300 text-gray-700 hover:bg-gray-50"
                    >
                        {cancelText}
                    </Button>
                    <Button 
                        variant={variant === 'danger' ? "destructive" : "default"} 
                        onClick={() => {
                            onConfirm();
                            onClose();
                        }}
                        className={`flex-1 ${variant === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-batik-900 hover:bg-batik-800'}`}
                    >
                        {confirmText}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
import React from 'react';
import { CheckCircle2, AlertTriangle, Info, X } from 'lucide-react';
import { useAppStore } from '../services/store';

export const ToastContainer: React.FC = () => {
  const { toasts, removeToast } = useAppStore();

  if (toasts.length === 0) return null;

  return (
    <div className="no-print fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm">
      {toasts.map((toast) => {
        const Icon =
          toast.type === 'success'
            ? CheckCircle2
            : toast.type === 'error'
            ? AlertTriangle
            : Info;

        const bg =
          toast.type === 'success'
            ? 'bg-emerald-600 text-white'
            : toast.type === 'error'
            ? 'bg-red-600 text-white'
            : 'bg-ict-navy text-white';

        return (
          <div
            key={toast.id}
            className={`flex items-center gap-2.5 px-3 py-2.5 rounded-lg shadow-lg text-xs font-medium animate-in slide-in-from-bottom-2 ${bg}`}
          >
            <Icon className="w-4 h-4 flex-shrink-0" />
            <span className="flex-1 leading-snug">{toast.message}</span>
            <button
              onClick={() => removeToast(toast.id)}
              className="p-1 hover:bg-black/10 rounded transition"
            >
              <X className="w-3.5 h-3.5" />
            </button>
          </div>
        );
      })}
    </div>
  );
};


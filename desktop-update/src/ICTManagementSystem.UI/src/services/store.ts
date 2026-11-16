import { create } from 'zustand';
import { User } from '../types';

interface AppState {
  user: User | null;
  token: string | null;
  theme: 'light' | 'dark';
  sidebarCollapsed: boolean;
  toasts: { id: string; type: 'success' | 'error' | 'info'; message: string }[];
  setUser: (user: User | null, token: string | null) => void;
  toggleTheme: () => void;
  toggleSidebar: () => void;
  addToast: (type: 'success' | 'error' | 'info', message: string) => void;
  removeToast: (id: string) => void;
}

export const useAppStore = create<AppState>((set, get) => ({
  user: JSON.parse(localStorage.getItem('ict_user') || 'null'),
  token: localStorage.getItem('ict_token') || null,
  theme: (localStorage.getItem('ict_theme') as 'light' | 'dark') || 'light',
  sidebarCollapsed: false,
  toasts: [],

  setUser: (user, token) => {
    if (user && token) {
      localStorage.setItem('ict_user', JSON.stringify(user));
      localStorage.setItem('ict_token', token);
    } else {
      localStorage.removeItem('ict_user');
      localStorage.removeItem('ict_token');
    }
    set({ user, token });
  },

  toggleTheme: () => {
    const next = get().theme === 'light' ? 'dark' : 'light';
    localStorage.setItem('ict_theme', next);
    if (next === 'dark') {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
    set({ theme: next });
  },

  toggleSidebar: () => set(state => ({ sidebarCollapsed: !state.sidebarCollapsed })),

  addToast: (type, message) => {
    const id = Math.random().toString(36).substring(2, 9);
    set(state => ({ toasts: [...state.toasts, { id, type, message }] }));
    setTimeout(() => get().removeToast(id), 4000);
  },

  removeToast: (id) => set(state => ({ toasts: state.toasts.filter(t => t.id !== id) }))
}));


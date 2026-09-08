import os

file_path = r"d:\MyProject\KaidNews\OlympicOEO\src\View\Screen\Disciplinary\DisciplinaryController.ts"

new_content = """import { useState, useMemo, useEffect } from 'react';
import { type DisciplinaryModel } from './disciplinary_data';
import { DISCIPLINARY } from '../../../LinkApi';
import axios from 'axios';

export const useDisciplinaryController = () => {
  const [disciplinaryList, setDisciplinaryList] = useState<DisciplinaryModel[]>([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [filterType, setFilterType] = useState<string>('الكل');
  const [filterStatus, setFilterStatus] = useState<string>('الكل');
  
  const [isAddDialogOpen, setIsAddDialogOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<DisciplinaryModel | null>(null);

  const fetchDisciplinary = async () => {
    try {
      const response = await axios.get(DISCIPLINARY, {
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
      });
      setDisciplinaryList(response.data);
    } catch (error) {
      console.error("Error fetching disciplinary records:", error);
    }
  };

  useEffect(() => {
    fetchDisciplinary();
  }, []);

  const openAddDialog = () => {
    setEditingItem(null);
    setIsAddDialogOpen(true);
  };

  const openEditDialog = (item: DisciplinaryModel) => {
    setEditingItem(item);
    setIsAddDialogOpen(true);
  };

  const closeDialog = () => {
    setIsAddDialogOpen(false);
    setEditingItem(null);
  };

  const handleSave = async (item: DisciplinaryModel) => {
    try {
      if (editingItem && editingItem.id) {
        await axios.post(`${DISCIPLINARY}/update`, item, {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
        });
      } else {
        await axios.post(`${DISCIPLINARY}/create`, item, {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
        });
      }
      await fetchDisciplinary();
      closeDialog();
    } catch (error) {
      console.error("Error saving disciplinary record:", error);
      alert("حدث خطأ أثناء الحفظ");
    }
  };

  const handleDelete = async (id: string) => {
    if (window.confirm('هل أنت متأكد من حذف هذا الإجراء التأديبي؟')) {
      try {
        await axios.post(`${DISCIPLINARY}/delete`, { id }, {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
        });
        await fetchDisciplinary();
      } catch (error) {
        console.error("Error deleting disciplinary record:", error);
        alert("حدث خطأ أثناء الحذف");
      }
    }
  };

  const handleUpdateStatus = async (id: string, newStatus: DisciplinaryModel['status']) => {
    try {
      const itemToUpdate = disciplinaryList.find(d => d.id === id);
      if (itemToUpdate) {
        await axios.post(`${DISCIPLINARY}/update`, { ...itemToUpdate, status: newStatus }, {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
        });
        await fetchDisciplinary();
      }
    } catch (error) {
      console.error("Error updating status:", error);
    }
  };

  const filteredList = useMemo(() => {
    return disciplinaryList.filter(item => {
      const matchesSearch = item.memberName.toLowerCase().includes(searchQuery.toLowerCase()) || 
                            item.reason.toLowerCase().includes(searchQuery.toLowerCase());
      const matchesType = filterType === 'الكل' || item.actionType === filterType;
      const matchesStatus = filterStatus === 'الكل' || item.status === filterStatus;
      
      return matchesSearch && matchesType && matchesStatus;
    });
  }, [disciplinaryList, searchQuery, filterType, filterStatus]);

  return {
    disciplinaryList: filteredList,
    searchQuery,
    setSearchQuery,
    filterType,
    setFilterType,
    filterStatus,
    setFilterStatus,
    isAddDialogOpen,
    editingItem,
    openAddDialog,
    openEditDialog,
    closeDialog,
    handleSave,
    handleDelete,
    handleUpdateStatus,
  };
};
"""

with open(file_path, "w", encoding="utf-8") as f:
    f.write(new_content)
    
print("Successfully updated DisciplinaryController.ts")

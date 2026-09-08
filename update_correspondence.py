import os

file_path = r"d:\MyProject\KaidNews\OlympicOEO\src\View\Screen\Correspondences\CorrespondencesController.ts"

new_content = """import { useState, useEffect } from 'react';
import { type CorrespondenceModel } from './correspondence_model';
import { MembersData } from '../Members/members_data';
import { MemberModel } from '../Members/member_model';
import { Crud } from '../../../core/class/Crud';
import { CORRESPONDENCES } from '../../../LinkApi';
import axios from 'axios';

export const useCorrespondencesController = () => {
  const [correspondences, setCorrespondences] = useState<CorrespondenceModel[]>([]);
  const [members, setMembers] = useState<MemberModel[]>([]);

  const crud = new Crud();
  const membersData = new MembersData(crud);
  
  // Filtering
  const [searchQuery, setSearchQuery] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  
  // Dialogs
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [selectedCorrespondence, setSelectedCorrespondence] = useState<CorrespondenceModel | null>(null);
  
  const [isAddDialogOpen, setIsAddDialogOpen] = useState(false);

  const fetchCorrespondences = async () => {
    try {
      const response = await axios.get(CORRESPONDENCES, {
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
      });
      setCorrespondences(response.data);
    } catch (error) {
      console.error("Error fetching correspondences:", error);
    }
  };

  useEffect(() => {
    fetchCorrespondences();
    
    const fetchMembers = async () => {
      const response = await membersData.getMembers();
      if (response) {
        if (Array.isArray(response)) {
          setMembers(response.data.map(MemberModel.fromJson));
        } else if (response.data && Array.isArray(response.data)) {
          setMembers(response.data.map(MemberModel.fromJson));
        }
      }
    };
    
    fetchMembers();
  }, []);

  const getMemberName = (memberId: number) => {
    const member = members.find(m => String(m.id) === String(memberId));
    return member ? `${member.first_name} ${member.last_name}` : 'غير معروف';
  };

  const getMember = (memberId: number) => {
    return members.find(m => String(m.id) === String(memberId));
  };

  const handleAddCorrespondence = async (data: Omit<CorrespondenceModel, 'id' | 'createdAt'>) => {
    try {
      await axios.post(`${CORRESPONDENCES}/create`, data, {
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
      });
      await fetchCorrespondences();
      setIsAddDialogOpen(false);
    } catch (error) {
      console.error("Error adding correspondence:", error);
      alert("حدث خطأ أثناء الإضافة");
    }
  };

  const handleUpdateStatus = async (id: string, status: CorrespondenceModel['status']) => {
    try {
      const itemToUpdate = correspondences.find(c => c.id === id);
      if (itemToUpdate) {
        await axios.post(`${CORRESPONDENCES}/update`, { ...itemToUpdate, status }, {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
        });
        await fetchCorrespondences();
      }
    } catch (error) {
      console.error("Error updating status:", error);
    }
  };

  const handleDelete = async (id: string) => {
    if (window.confirm("هل أنت متأكد من حذف هذه المراسلة؟")) {
      try {
        await axios.post(`${CORRESPONDENCES}/delete`, { id }, {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
        });
        await fetchCorrespondences();
      } catch (error) {
        console.error("Error deleting correspondence:", error);
        alert("حدث خطأ أثناء الحذف");
      }
    }
  };

  const openAddDialog = () => {
    setIsAddDialogOpen(true);
  };

  const closeAddDialog = () => {
    setIsAddDialogOpen(false);
  };

  const openViewDialog = (correspondence: CorrespondenceModel) => {
    setSelectedCorrespondence(correspondence);
    setIsDialogOpen(true);
  };

  const closeViewDialog = () => {
    setSelectedCorrespondence(null);
    setIsDialogOpen(false);
  };

  const filteredCorrespondences = correspondences.filter(c => {
    const memberName = getMemberName(c.memberId)?.toLowerCase() || '';
    const search = searchQuery.toLowerCase();
    const matchesSearch = memberName.includes(search) || 
                          (c.subject?.toLowerCase() || '').includes(search) ||
                          (c.correspondenceNumber?.toLowerCase() || '').includes(search);
    
    const matchesStatus = filterStatus ? c.status === filterStatus : true;
    
    return matchesSearch && matchesStatus;
  });

  return {
    correspondences: filteredCorrespondences,
    members,
    searchQuery,
    setSearchQuery,
    filterStatus,
    setFilterStatus,
    getMemberName,
    getMember,
    handleAddCorrespondence,
    handleUpdateStatus,
    handleDelete,
    isDialogOpen,
    isAddDialogOpen,
    selectedCorrespondence,
    openAddDialog,
    closeAddDialog,
    openViewDialog,
    closeViewDialog
  };
};
"""

with open(file_path, "w", encoding="utf-8") as f:
    f.write(new_content)
    
print("Successfully updated CorrespondencesController.ts")
